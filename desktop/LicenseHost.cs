using System;
using System.Collections.Generic;
using System.IO;
using System.Management;
using System.Net;
using System.Reflection;
using System.Security.Cryptography;
using System.Text;
using System.Web.Script.Serialization;
using Microsoft.Win32;

internal static class LicenseHost {
    static readonly JavaScriptSerializer Json = new JavaScriptSerializer { MaxJsonLength = 1024 * 1024 };
    static int Main(string[] args) {
        try {
            if (args.Length < 1) throw new Exception("Missing licence command.");
            switch (args[0]) {
                case "device": Console.Write(Json.Serialize(Device())); return 0;
                case "post": Console.Write(Post(args[1], File.ReadAllText(args[2]))); return 0;
                case "protect": Protect(File.ReadAllBytes(args[1]), args[2]); return 0;
                case "unprotect": Console.Write(Encoding.UTF8.GetString(Unprotect(args[1]))); return 0;
                case "install": Install(args[1], args[2]); return 0;
                case "verify": Console.Write(Json.Serialize(Verify(args[1]))); return 0;
                default: throw new Exception("Unknown licence command.");
            }
        } catch (FileNotFoundException e) { Console.Error.Write(e.Message); return 10; }
          catch (Exception e) { Console.Error.Write(e.Message); return 20; }
    }

    static Dictionary<string,object> Device() {
        string machine = ReadRegistry(), uuid = Query("SELECT UUID FROM Win32_ComputerSystemProduct", "UUID"), volume = Query("SELECT VolumeSerialNumber FROM Win32_LogicalDisk WHERE DeviceID='C:'", "VolumeSerialNumber");
        string canonical = (machine + "|" + uuid + "|" + volume).Trim().ToLowerInvariant();
        if (canonical.Replace("|", "").Length < 8) throw new Exception("Could not identify this PC.");
        return new Dictionary<string,object> {
            {"device_id", Hex(SHA256.Create().ComputeHash(Encoding.UTF8.GetBytes("AI-Billing-Desktop-v1|" + canonical)))},
            {"pc_name", Environment.MachineName}, {"windows_version", Environment.OSVersion.VersionString},
            {"windows_user", Environment.UserName}, {"architecture", Environment.Is64BitOperatingSystem ? "x64" : "x86"}
        };
    }
    static string ReadRegistry() { try { using (var k=Registry.LocalMachine.OpenSubKey(@"SOFTWARE\Microsoft\Cryptography")) return Convert.ToString(k.GetValue("MachineGuid")) ?? ""; } catch { return ""; } }
    static string Query(string sql,string property) { try { foreach(ManagementObject o in new ManagementObjectSearcher(sql).Get()) return Convert.ToString(o[property]) ?? ""; } catch {} return ""; }
    static string Hex(byte[] b) { return BitConverter.ToString(b).Replace("-", "").ToLowerInvariant(); }
    static string Post(string url,string body) {
        if (!url.StartsWith("https://billing.cloudkart24.com/api/desktop-license/", StringComparison.OrdinalIgnoreCase)) throw new Exception("Untrusted activation server.");
        ServicePointManager.SecurityProtocol = SecurityProtocolType.Tls12;
        var req=(HttpWebRequest)WebRequest.Create(url); req.Method="POST";req.ContentType="application/json";req.UserAgent="AI-Billing-Offline/1.0";req.Timeout=30000;
        byte[] data=Encoding.UTF8.GetBytes(body);req.ContentLength=data.Length;using(var s=req.GetRequestStream())s.Write(data,0,data.Length);
        try { using(var res=(HttpWebResponse)req.GetResponse())using(var reader=new StreamReader(res.GetResponseStream()))return reader.ReadToEnd(); }
        catch(WebException e) { if(e.Response!=null)using(var reader=new StreamReader(e.Response.GetResponseStream()))throw new Exception(reader.ReadToEnd());throw; }
    }
    static void Protect(byte[] value,string destination) {
        Directory.CreateDirectory(Path.GetDirectoryName(destination)); byte[] protectedValue=ProtectedData.Protect(value,Encoding.UTF8.GetBytes("AI Billing Offline Licence v1"),DataProtectionScope.CurrentUser);
        string temp=destination+"."+Guid.NewGuid().ToString("N")+".tmp";File.WriteAllBytes(temp,protectedValue);if(File.Exists(destination))File.Replace(temp,destination,destination+".bak",true);else File.Move(temp,destination);
    }
    static byte[] Unprotect(string path) { if(!File.Exists(path))throw new FileNotFoundException("Protected licence data is missing.");return ProtectedData.Unprotect(File.ReadAllBytes(path),Encoding.UTF8.GetBytes("AI Billing Offline Licence v1"),DataProtectionScope.CurrentUser); }
    static string PublicKey() { using(Stream s=Assembly.GetExecutingAssembly().GetManifestResourceStream("license-public-key.xml"))using(var r=new StreamReader(s))return r.ReadToEnd().Trim(); }
    static void Install(string responseFile,string dataRoot) {
        var outer=Json.DeserializeObject(File.ReadAllText(responseFile)) as Dictionary<string,object>;var data=outer.ContainsKey("data")?(Dictionary<string,object>)outer["data"]:outer;
        if(!String.Equals(Convert.ToString(data["public_key"]).Trim(),PublicKey(),StringComparison.Ordinal))throw new Exception("Activation server public key does not match this installer.");
        var envelope=new Dictionary<string,object>{{"license_document",data["license_document"]},{"public_key",data["public_key"]},{"license_id",data["license_id"]}};
        string temp=Path.GetTempFileName();try{File.WriteAllText(temp,Json.Serialize(envelope));VerifyDocument(Json.Serialize(envelope),false);Protect(File.ReadAllBytes(temp),Path.Combine(dataRoot,"license.dat"));}finally{File.Delete(temp);}
    }
    static Dictionary<string,object> Verify(string dataRoot) { return VerifyDocument(Encoding.UTF8.GetString(Unprotect(Path.Combine(dataRoot,"license.dat"))),true); }
    static Dictionary<string,object> VerifyDocument(string envelopeJson,bool requireActive) {
        var envelope=(Dictionary<string,object>)Json.DeserializeObject(envelopeJson);if(!String.Equals(Convert.ToString(envelope["public_key"]).Trim(),PublicKey(),StringComparison.Ordinal))throw new Exception("Licence signing key mismatch.");
        var document=(Dictionary<string,object>)Json.DeserializeObject(Convert.ToString(envelope["license_document"]));byte[] payload=Base64Url(Convert.ToString(document["payload"]));byte[] signature=Base64Url(Convert.ToString(document["signature"]));
        using(var rsa=new RSACryptoServiceProvider()){rsa.FromXmlString(PublicKey());if(!rsa.VerifyData(payload,CryptoConfig.MapNameToOID("SHA256"),signature))throw new Exception("Licence signature is invalid.");}
        var claims=(Dictionary<string,object>)Json.DeserializeObject(Encoding.UTF8.GetString(payload));var device=Device();if(!String.Equals(Convert.ToString(claims["device_id"]),Convert.ToString(device["device_id"]),StringComparison.OrdinalIgnoreCase))throw new Exception("Licence belongs to another PC.");
        bool active=String.Equals(Convert.ToString(claims["status"]),"active",StringComparison.OrdinalIgnoreCase);
        if(claims.ContainsKey("expires_at")&&claims["expires_at"]!=null&&Convert.ToInt64(claims["expires_at"])<DateTimeOffset.UtcNow.ToUnixTimeSeconds())active=false;
        if(requireActive&&!active)throw new Exception("Licence is not active.");
        return new Dictionary<string,object>{{"active",active},{"license_id",claims["license_id"]},{"claims",claims},{"device",device}};
    }
    static byte[] Base64Url(string s){s=s.Replace('-','+').Replace('_','/');switch(s.Length%4){case 2:s+="==";break;case 3:s+="=";break;}return Convert.FromBase64String(s);}
}
