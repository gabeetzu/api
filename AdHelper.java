package com.secretele.gospodarului;

import android.app.Activity;
import android.content.Context;

import com.google.android.gms.ads.AdRequest;
import com.google.android.gms.ads.rewarded.RewardedAd;
import com.google.android.gms.ads.rewarded.RewardedAdLoadCallback;
import androidx.annotation.NonNull;

public class AdHelper {
    private static RewardedAd rewardedAd;

    public static void preload(Context context) {
        if (rewardedAd != null) return;
        RewardedAd.load(
                context,
                context.getString(R.string.admob_rewarded_id),
                new AdRequest.Builder().build(),
                new RewardedAdLoadCallback() {
                    @Override
                    public void onAdLoaded(@NonNull RewardedAd ad) {
                        rewardedAd = ad;
                    }

                    @Override
                    public void onAdFailedToLoad(@NonNull com.google.android.gms.ads.LoadAdError adError) {
                        rewardedAd = null;
                    }
                }
        );
    }

    public static boolean show(Activity activity, Runnable onRewarded) {
        if (rewardedAd == null) return false;
        RewardedAd ad = rewardedAd;
        rewardedAd = null;
        ad.show(activity, rewardItem -> {
            if (onRewarded != null) onRewarded.run();
            preload(activity.getApplicationContext());
        });
        return true;
    }
}