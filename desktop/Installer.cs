using System;
using System.IO;
using System.Diagnostics;
using System.Reflection;
using System.Windows.Forms;

internal static class Installer {
    [STAThread]
    static void Main() {
        string temp = Path.Combine(Path.GetTempPath(), "AIBillingInstall-" + Guid.NewGuid().ToString("N"));
        try {
            Directory.CreateDirectory(temp);
            foreach (string name in new[] {"payload.zip", "Install.ps1"}) {
                using (Stream input = Assembly.GetExecutingAssembly().GetManifestResourceStream(name))
                using (FileStream output = File.Create(Path.Combine(temp, name))) input.CopyTo(output);
            }
            ProcessStartInfo start = new ProcessStartInfo("powershell.exe",
                "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File \"" + Path.Combine(temp, "Install.ps1") + "\" -Payload \"" + Path.Combine(temp, "payload.zip") + "\"");
            start.UseShellExecute = false; start.CreateNoWindow = true;
            using (Process process = Process.Start(start)) process.WaitForExit();
        } catch (Exception error) { MessageBox.Show(error.Message, "AI Billing installation failed"); }
        finally { try { Directory.Delete(temp, true); } catch {} }
    }
}
