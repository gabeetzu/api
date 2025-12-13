# Play Store Trusted Web Activity (TWA) Notes

To publish Infinity VR Companion as a Trusted Web Activity on the Google Play Store, you need to host a Digital Asset Links file that verifies ownership of the domain and allows the Android app to handle all URLs.

## Digital Asset Links configuration

Host the following JSON at `https://infinityvrgaming.com/.well-known/assetlinks.json`:

```json
[
  {
    "relation": [
      "delegate_permission/common.handle_all_urls"
    ],
    "target": {
      "namespace": "android_app",
      "package_name": "<your.package.name>",
      "sha256_cert_fingerprints": [
        "<SHA256_CERT_FINGERPRINT>"
      ]
    }
  }
]
```

- Replace `<your.package.name>` with the Android application ID.
- Replace `<SHA256_CERT_FINGERPRINT>` with the SHA-256 fingerprint(s) for the signing certificate(s) used by your app (add additional entries if needed).
- Keep the JSON served from the `/.well-known/assetlinks.json` path to satisfy TWA verification.
