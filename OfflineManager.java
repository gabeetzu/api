package com.secretele.gospodarului;

import android.content.Context;
import android.content.SharedPreferences;

import com.google.gson.Gson;
import com.google.gson.JsonObject;
import com.google.gson.reflect.TypeToken;

import java.lang.reflect.Type;
import java.util.ArrayList;
import java.util.Iterator;
import java.util.List;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * Handles storing user requests when offline and retrying them later.
 * Also keeps a small cache of the last few bot responses for offline viewing.
 */
public class OfflineManager {
    private static final String PREF_NAME = "offline_queue";
    private static final String KEY_QUEUE = "queue";
    private static final String KEY_CACHE = "cache";

    private final SharedPreferences prefs;
    private final Gson gson = new Gson();

    public OfflineManager(Context context) {
        this.prefs = context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE);
    }

    // --- Queue Management ---
    public void enqueue(JsonObject request) {
        List<JsonObject> queue = getQueue();
        queue.add(request);
        saveQueue(queue);
    }

    public void retry(ApiService api, Callback<JsonObject> callback) {
        new Thread(() -> {
            List<JsonObject> queue = getQueue();
            Iterator<JsonObject> it = queue.iterator();
            while (it.hasNext()) {
                JsonObject req = it.next();
                try {
                    Response<JsonObject> resp = api.processRequest(req).execute();
                    if (resp.isSuccessful() && resp.body() != null) {
                        if (callback != null) callback.onResponse(api.processRequest(req), resp);
                        it.remove();
                    } else {
                        break; // stop on first failure
                    }
                } catch (Exception e) {
                    if (callback != null) callback.onFailure(api.processRequest(req), e);
                    break;
                }
            }
            saveQueue(queue);
        }).start();
    }

    private List<JsonObject> getQueue() {
        String json = prefs.getString(KEY_QUEUE, "[]");
        Type t = new TypeToken<List<JsonObject>>(){}.getType();
        List<JsonObject> list = gson.fromJson(json, t);
        if (list == null) list = new ArrayList<>();
        return list;
    }

    private void saveQueue(List<JsonObject> q) {
        prefs.edit().putString(KEY_QUEUE, gson.toJson(q)).apply();
    }

    // --- Response Cache ---
    public void cacheResponse(String responseText) {
        List<String> cache = getCachedResponses();
        cache.add(responseText);
        if (cache.size() > 3) {
            cache = cache.subList(cache.size() - 3, cache.size());
        }
        prefs.edit().putString(KEY_CACHE, gson.toJson(cache)).apply();
    }

    public List<String> getCachedResponses() {
        String json = prefs.getString(KEY_CACHE, "[]");
        Type t = new TypeToken<List<String>>(){}.getType();
        List<String> list = gson.fromJson(json, t);
        return list != null ? list : new ArrayList<>();
    }
}