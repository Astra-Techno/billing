using System;
using System.IO;
using System.Net;
using System.Net.Security;
using System.Net.Sockets;
using System.Security.Cryptography;
using System.Security.Cryptography.X509Certificates;
using System.Threading;

/// <summary>
/// Lightweight TLS reverse proxy. Terminates HTTPS on the LAN-facing port
/// and forwards plain HTTP to the local PHP built-in server.
/// Auto-generates a self-signed certificate on first run.
/// </summary>
class TlsProxy
{
    static string certPath;
    static string backendHost;
    static int backendPort;

    static int Main(string[] args)
    {
        if (args.Length < 3)
        {
            Console.Error.WriteLine("Usage: TlsProxy <listenPort> <backendPort> <certDir>");
            return 1;
        }
        int listenPort = int.Parse(args[0]);
        backendPort = int.Parse(args[1]);
        backendHost = "127.0.0.1";
        string certDir = args[2];
        certPath = Path.Combine(certDir, "tls-cert.pfx");

        X509Certificate2 cert = GetOrCreateCert();
        var listener = new TcpListener(IPAddress.Any, listenPort);
        listener.Start();
        Console.WriteLine("TLS proxy listening on :" + listenPort + " -> " + backendHost + ":" + backendPort);
        Console.Out.Flush();

        while (true)
        {
            TcpClient client;
            try { client = listener.AcceptTcpClient(); }
            catch { break; }
            ThreadPool.QueueUserWorkItem(_ => HandleClient(client, cert));
        }
        return 0;
    }

    static void HandleClient(TcpClient client, X509Certificate2 cert)
    {
        try
        {
            client.ReceiveTimeout = 30000;
            client.SendTimeout = 30000;
            using (var sslStream = new SslStream(client.GetStream(), false))
            {
                sslStream.AuthenticateAsServer(cert, false, System.Security.Authentication.SslProtocols.Tls12 | System.Security.Authentication.SslProtocols.Tls13, false);
                using (var backend = new TcpClient(backendHost, backendPort))
                {
                    backend.ReceiveTimeout = 60000;
                    backend.SendTimeout = 30000;
                    var backendStream = backend.GetStream();

                    var t1 = new Thread(() => Relay(sslStream, backendStream)) { IsBackground = true };
                    var t2 = new Thread(() => Relay(backendStream, sslStream)) { IsBackground = true };
                    t1.Start();
                    t2.Start();
                    t1.Join();
                    // Give response relay time to complete after request side closes
                    t2.Join(TimeSpan.FromSeconds(5));
                }
            }
        }
        catch { }
        finally
        {
            try { client.Close(); } catch { }
        }
    }

    static void Relay(Stream from, Stream to)
    {
        try
        {
            byte[] buf = new byte[8192];
            int n;
            while ((n = from.Read(buf, 0, buf.Length)) > 0)
            {
                to.Write(buf, 0, n);
                to.Flush();
            }
        }
        catch { }
    }

    static X509Certificate2 GetOrCreateCert()
    {
        if (File.Exists(certPath))
        {
            try { return new X509Certificate2(certPath, "aibilling"); }
            catch { /* regenerate if corrupt */ }
        }

        Console.WriteLine("Generating self-signed TLS certificate...");
        using (var rsa = RSA.Create(2048))
        {
            var req = new CertificateRequest(
                "CN=AI Billing LAN, O=AI Billing",
                rsa,
                HashAlgorithmName.SHA256,
                RSASignaturePadding.Pkcs1);

            // Subject Alternative Names — cover IP and hostname access patterns
            var san = new SubjectAlternativeNameBuilder();
            san.AddDnsName(Environment.MachineName);
            san.AddDnsName("localhost");
            san.AddIpAddress(IPAddress.Loopback);
            // Add all local IPv4 addresses
            foreach (var addr in Dns.GetHostAddresses(Dns.GetHostName()))
            {
                if (addr.AddressFamily == AddressFamily.InterNetwork)
                    san.AddIpAddress(addr);
            }
            req.CertificateExtensions.Add(san.Build());

            req.CertificateExtensions.Add(
                new X509BasicConstraintsExtension(false, false, 0, false));
            req.CertificateExtensions.Add(
                new X509KeyUsageExtension(
                    X509KeyUsageFlags.DigitalSignature | X509KeyUsageFlags.KeyEncipherment, false));
            req.CertificateExtensions.Add(
                new X509EnhancedKeyUsageExtension(
                    new OidCollection { new Oid("1.3.6.1.5.5.7.3.1") }, false)); // serverAuth

            var cert = req.CreateSelfSigned(DateTimeOffset.UtcNow, DateTimeOffset.UtcNow.AddYears(5));
            byte[] pfxBytes = cert.Export(X509ContentType.Pfx, "aibilling");
            Directory.CreateDirectory(Path.GetDirectoryName(certPath));
            File.WriteAllBytes(certPath, pfxBytes);
            Console.WriteLine("Certificate saved to " + certPath);
            return new X509Certificate2(pfxBytes, "aibilling");
        }
    }
}
