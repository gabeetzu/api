package com.secretele.gospodarului;

import android.content.Context;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;

import java.io.InputStream;
import java.io.InputStreamReader;
import java.util.ArrayList;
import java.util.List;

public class OfflineContentProvider {
    private final Context context;
    private List<String> faq = new ArrayList<>();

    public OfflineContentProvider(Context ctx) {
        this.context = ctx.getApplicationContext();
        load();
    }

    private void load() {
        try {
            InputStream is = context.getAssets().open("offline_content.json");
            JsonObject obj = JsonParser.parseReader(new InputStreamReader(is)).getAsJsonObject();
            JsonArray arr = obj.getAsJsonArray("faq");
            if (arr != null) {
                for (JsonElement el : arr) {
                    faq.add(el.getAsString());
                }
            }
            is.close();
        } catch (Exception e) {
            // ignore, leave faq empty
        }
    }
    public List<String> getFaq() {
        return faq;
    }
}