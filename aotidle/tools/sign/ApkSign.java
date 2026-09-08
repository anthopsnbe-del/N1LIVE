/* Signature d'un APK (schéma v2) avec la bibliothèque apksig d'Android.
   Usage : ApkSign <entrée.apk> <sortie.apk> <keystore.jks> <mdp> <alias> */
import com.android.apksig.ApkSigner;
import com.android.apksig.ApkVerifier;

import java.io.File;
import java.security.KeyStore;
import java.security.PrivateKey;
import java.security.cert.X509Certificate;
import java.util.ArrayList;
import java.util.Collections;
import java.util.List;

public final class ApkSign {
  public static void main(String[] args) throws Exception {
    File in = new File(args[0]);
    File out = new File(args[1]);
    char[] pass = args[3].toCharArray();

    KeyStore ks = KeyStore.getInstance("PKCS12");
    try (java.io.FileInputStream fis = new java.io.FileInputStream(args[2])) {
      ks.load(fis, pass);
    }
    String alias = args[4];
    PrivateKey key = (PrivateKey) ks.getKey(alias, pass);
    java.security.cert.Certificate[] chain = ks.getCertificateChain(alias);
    List<X509Certificate> certs = new ArrayList<>();
    for (java.security.cert.Certificate c : chain) certs.add((X509Certificate) c);

    ApkSigner.SignerConfig signer =
        new ApkSigner.SignerConfig.Builder("CERT", key, certs).build();
    new ApkSigner.Builder(Collections.singletonList(signer))
        .setInputApk(in)
        .setOutputApk(out)
        // v1 (JAR) est hors service sur les JDK modernes et inutile ici :
        // minSdkVersion 24 accepte le schéma v2 seul.
        .setV1SigningEnabled(false)
        .setV2SigningEnabled(true)
        .build()
        .sign();
    ApkVerifier.Result result = new ApkVerifier.Builder(out).setMinCheckedPlatformVersion(24).build().verify();
    if (!result.isVerified()) {
      throw new IllegalStateException("signature invalide : " + result.getErrors());
    }
    System.out.println("APK signé et vérifié (v2=" + result.isVerifiedUsingV2Scheme() + ")");
  }
}
