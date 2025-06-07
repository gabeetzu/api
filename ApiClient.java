package com.secretele.gospodarului;

import okhttp3.OkHttpClient;
import okhttp3.logging.HttpLoggingInterceptor;
import retrofit2.Retrofit;
import retrofit2.converter.gson.GsonConverterFactory;
import com.google.gson.Gson;
import com.google.gson.GsonBuilder;
import java.util.concurrent.TimeUnit;

public class ApiClient {
    private static final String BASE_URL = "https://gabeetzu-project.onrender.com/";
    private static final String API_KEY = "7s$!nVpWb3YqZt6w9z$C&F)J@NcRfUjXn";
    public static final String OPEN_WEATHER_KEY = "fc32e89a6779a53497f25a690a4d6398";
    private static Retrofit retrofit = null;

    // Remove singleton instance for ApiClient (not needed)
    // Private constructor to prevent instantiation
    private ApiClient() {
        throw new IllegalStateException("Utility class");
    }

    public static ApiService getApiService() {
        return getClient().create(ApiService.class);
    }

    private static synchronized Retrofit getClient() {
        if (retrofit == null) {
            HttpLoggingInterceptor logging = new HttpLoggingInterceptor();
            logging.setLevel(HttpLoggingInterceptor.Level.BASIC); // Reduced from BODY for security

            Gson gson = new GsonBuilder()
                    .setLenient()
                    .create();

            OkHttpClient client = new OkHttpClient.Builder()
                    .connectTimeout(30, TimeUnit.SECONDS)
                    .readTimeout(30, TimeUnit.SECONDS)
                    .writeTimeout(30, TimeUnit.SECONDS)
                    .addInterceptor(chain -> {
                        var request = chain.request().newBuilder()
                                .addHeader("X-API-Key", API_KEY)
                                .build();
                        return chain.proceed(request);
                    })
                    .addInterceptor(logging)
                    .build();

            retrofit = new Retrofit.Builder()
                    .baseUrl(BASE_URL)
                    .client(client)
                    .addConverterFactory(GsonConverterFactory.create(gson))
                    .build();
        }
        return retrofit;
    }
}
