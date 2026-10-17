# Windows installer code signing

AI Billing's installer must be signed with a trusted **OV or EV Authenticode code-signing certificate** issued to the selling business. The private key must remain with the publisher; it cannot be generated or embedded in this repository.

1. Buy an Authenticode certificate from a Windows-trusted certificate authority.
2. Install the certificate and private key in the Windows certificate store on the release computer or connect its hardware token.
3. Install the Windows SDK signing tools.
4. Build the installer with `npm.cmd run build:offline`.
5. Run:

   ```powershell
   powershell -ExecutionPolicy Bypass -File desktop\Sign-Release.ps1 `
     -Installer dist\offline-YYYYMMDD-HHMMSS\AI-Billing-Offline-Setup.exe `
     -CertificateThumbprint YOUR_40_CHARACTER_THUMBPRINT
   ```

6. Copy the newly signed installer to `public_html\downloads\AI-Billing-Offline-Setup.exe` and deploy it.
7. Check **Properties → Digital Signatures** on a separate Windows PC and confirm the signer and timestamp are valid.

Signing changes the installer hash. Publish the SHA-256 printed by the script only after signing.
