package com.secretele.gospodarului;

/** Utility holder for build time constants loaded from environment. */
public final class BuildConfig {
    public static final String BASE_URL = System.getenv("GOSPOD_BASE_URL");
    public static final String API_KEY = System.getenv("GOSPOD_API_KEY");
    public static final String OPEN_WEATHER_KEY = System.getenv("OPEN_WEATHER_KEY");
    private BuildConfig() {}
}
