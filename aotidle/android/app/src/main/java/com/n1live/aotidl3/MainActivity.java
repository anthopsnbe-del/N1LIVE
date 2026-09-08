package com.n1live.aotidl3;

import android.app.AlarmManager;
import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.Context;
import android.content.Intent;
import android.os.Build;
import android.os.Bundle;
import android.webkit.JavascriptInterface;
import android.webkit.WebResourceRequest;
import android.webkit.WebView;
import android.webkit.WebViewClient;

import androidx.appcompat.app.AppCompatActivity;

/** Coquille du jeu : une WebView plein écran, plus un pont pour les rappels. */
public class MainActivity extends AppCompatActivity {

    static final String CHANNEL_ID = "aot-idle-rappels";
    private WebView web;

    @Override
    protected void onCreate(Bundle state) {
        super.onCreate(state);
        createChannel();

        web = new WebView(this);
        web.getSettings().setJavaScriptEnabled(true);
        web.getSettings().setDomStorageEnabled(true);
        web.getSettings().setMediaPlaybackRequiresUserGesture(false);
        web.addJavascriptInterface(new AotBridge(this), "AotBridge");
        web.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                // Les liens externes partent dans le navigateur, le jeu reste ici.
                if (request.isForMainFrame() && !request.getUrl().toString().startsWith("file://")) {
                    startActivity(new Intent(Intent.ACTION_VIEW, request.getUrl()));
                    return true;
                }
                return false;
            }
        });
        web.loadUrl("file:///android_asset/index.html");
        setContentView(web);
    }

    @Override
    protected void onResume() {
        super.onResume();
        // Le jeu écoute cet événement pour rafraîchir ses écrans en ligne.
        web.evaluateJavascript("window.dispatchEvent(new Event('aot-resume'))", null);
    }

    private void createChannel() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return;
        NotificationChannel channel = new NotificationChannel(
                CHANNEL_ID, "Rappels du bataillon", NotificationManager.IMPORTANCE_DEFAULT);
        channel.setDescription("Boss mondial, guerres de clans et missions du jour.");
        getSystemService(NotificationManager.class).createNotificationChannel(channel);
    }

    /** Ce que le JavaScript peut appeler. Rien d'autre n'est exposé. */
    public static class AotBridge {
        private final Context context;

        AotBridge(Context context) { this.context = context; }

        /**
         * Programme une notification locale.
         *
         * @param message texte affiché (tronqué à 120 caractères)
         * @param delayMs délai en millisecondes, borné à 7 jours
         */
        @JavascriptInterface
        public void schedule(String message, long delayMs) {
            if (message == null || message.isEmpty()) return;
            long delay = Math.max(60000L, Math.min(delayMs, 7L * 24 * 3600 * 1000));
            Intent intent = new Intent(context, AlarmReceiver.class);
            intent.putExtra("message", message.substring(0, Math.min(120, message.length())));
            int id = message.hashCode();
            PendingIntent pending = PendingIntent.getBroadcast(context, id, intent,
                    PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE);
            AlarmManager alarms = context.getSystemService(AlarmManager.class);
            alarms.set(AlarmManager.RTC, System.currentTimeMillis() + delay, pending);
        }

        /** Annule tous les rappels d'un même texte. */
        @JavascriptInterface
        public void cancel(String message) {
            if (message == null) return;
            Intent intent = new Intent(context, AlarmReceiver.class);
            PendingIntent pending = PendingIntent.getBroadcast(context, message.hashCode(), intent,
                    PendingIntent.FLAG_NO_CREATE | PendingIntent.FLAG_IMMUTABLE);
            if (pending != null) context.getSystemService(AlarmManager.class).cancel(pending);
        }

        /** Le jeu s'en sert pour savoir s'il peut proposer les rappels. */
        @JavascriptInterface
        public boolean available() { return true; }
    }
}
