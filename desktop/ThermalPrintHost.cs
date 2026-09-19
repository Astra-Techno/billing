using System;
using System.IO;
using System.IO.Ports;
using System.Management;
using Microsoft.Win32;
using System.Text;
using System.Text.RegularExpressions;

internal static class ThermalPrintHost
{
    private static int Main(string[] args)
    {
        if (args.Length != 1 || (args[0] != "--detect" && args[0] != "--auto" && !Regex.IsMatch(args[0], @"^COM([1-9]|[1-9][0-9])$", RegexOptions.IgnoreCase)))
        {
            Console.Error.WriteLine("A valid COM port is required.");
            return 2;
        }
        try
        {
            if (args[0] == "--detect")
            {
                Console.WriteLine(DetectPort());
                return 0;
            }
            var selectedPort = args[0] == "--auto" ? DetectPort() : args[0].ToUpperInvariant();
            using (var input = new MemoryStream())
            {
                Console.OpenStandardInput().CopyTo(input);
                if (input.Length == 0 || input.Length > 65536) throw new InvalidOperationException("Invalid print job size.");
                using (var port = new SerialPort(selectedPort, 9600, Parity.None, 8, StopBits.One))
                {
                    port.WriteTimeout = 5000;
                    port.Open();
                    var bytes = input.ToArray();
                    port.Write(bytes, 0, bytes.Length);
                }
            }
            return 0;
        }
        catch (Exception e)
        {
            Console.Error.WriteLine(e.Message);
            return 1;
        }
    }

    private static string DetectPort()
    {
        using (var devices = Registry.LocalMachine.OpenSubKey(@"SYSTEM\CurrentControlSet\Services\BTHPORT\Parameters\Devices"))
        {
            if (devices == null) throw new InvalidOperationException("No paired Bluetooth devices found.");
            foreach (var address in devices.GetSubKeyNames())
            {
                using (var device = devices.OpenSubKey(address))
                {
                    var nameBytes = device == null ? null : device.GetValue("Name") as byte[];
                    var name = nameBytes == null ? "" : Encoding.UTF8.GetString(nameBytes).TrimEnd('\0');
                    if (name.IndexOf("PSF588", StringComparison.OrdinalIgnoreCase) < 0 && name.IndexOf("SC588", StringComparison.OrdinalIgnoreCase) < 0) continue;
                    using (var search = new ManagementObjectSearcher("SELECT Name, PNPDeviceID FROM Win32_PnPEntity WHERE Name LIKE '%(COM%'"))
                    {
                        foreach (ManagementObject port in search.Get())
                        {
                            var id = Convert.ToString(port["PNPDeviceID"]) ?? "";
                            var label = Convert.ToString(port["Name"]) ?? "";
                            if (id.IndexOf(address, StringComparison.OrdinalIgnoreCase) < 0) continue;
                            var match = Regex.Match(label, @"\((COM[1-9][0-9]?)\)", RegexOptions.IgnoreCase);
                            if (match.Success) return match.Groups[1].Value.ToUpperInvariant();
                        }
                    }
                }
            }
        }
        throw new InvalidOperationException("PSF588 is not paired with a Bluetooth serial port. Pair it in Windows Bluetooth settings.");
    }
}
