package com.secretele.gospodarului;

import okhttp3.OkHttpClient;
import okhttp3.logging.HttpLoggingInterceptor;
import retrofit2.Retrofit;
import retrofit2.converter.gson.GsonConverterFactory;
import com.google.gson.Gson;
import com.google.gson.GsonBuilder;

import java.util.concurrent.TimeUnit;

public class ApiClient {

    // Load environment-safe config values from BuildConfig
    private static final String BASE_URL = BuildConfig.BASE_URL != null
            ? BuildConfig.BASE_URL : "https://example.com/"; // fallback if unset
    private static final String API_KEY = BuildConfig.API_KEY;
    public static final String OPEN_WEATHER_KEY = BuildConfig.OPEN_WEATHER_KEY;

    private static Retrofit retrofit = null;

    // Prevent instantiation
    private ApiClient() {
        throw new IllegalStateException("Utility class");
    }

    public static ApiService getApiService() {
        return getClient().create(ApiService.class);
    }

    private static synchronized Retrofit getClient() {
        if (retrofit == null) {
            // Log basic request info, no body to protect secrets
            HttpLoggingInterceptor logging = new HttpLoggingInterceptor();
            logging.setLevel(HttpLoggingInterceptor.Level.BASIC);

            // Use a lenient GSON parser
            Gson gson = new GsonBuilder()
                    .setLenient()
                    .create();

            // Configure OkHttp with timeouts and secure header injection
            OkHttpClient client = new OkHttpClient.Builder()
                    .connectTimeout(30, TimeUnit.SECONDS)
                    .readTimeout(30, TimeUnit.SECONDS)
                    .writeTimeout(30, TimeUnit.SECONDS)
                    .addInterceptor(chain -> {
                        return chain.proceed(
                                chain.request().newBuilder()
                                        .addHeader("X-API-Key", API_KEY)
                                        .build()
                        );
                    })
                    .addInterceptor(logging)
                    .build();

            // Build Retrofit instance
            retrofit = new Retrofit.Builder()
                    .baseUrl(BASE_URL)
                    .client(client)
                    .addConverterFactory(GsonConverterFactory.create(gson))
                    .build();
        }
        return retrofit;
    }
}
