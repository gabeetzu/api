plugins {
    id("com.android.application")
    id("com.google.gms.google-services")
}

android {
    namespace = "com.secretele.gospodarului"
    compileSdk = 34

    defaultConfig {
        applicationId = "com.secretele.gospodarului"
        minSdk = 21
        targetSdk = 34
        versionCode = 1
        versionName = "1.0"
        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"
    }

    buildFeatures {
        viewBinding = true
    }

    buildTypes {
        getByName("release") {
            isMinifyEnabled = false
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro"
            )
        }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_11
        targetCompatibility = JavaVersion.VERSION_11
    }

    packaging {
        resources {
            excludes += setOf(
                "META-INF/DEPENDENCIES",
                "META-INF/LICENSE",
                "META-INF/LICENSE.txt",
                "META-INF/license.txt",
                "META-INF/NOTICE",
                "META-INF/NOTICE.txt",
                "META-INF/notice.txt",
                "META-INF/ASL2.0"
            )
        }
    }
}

dependencies {
    // Core Android libraries
    implementation("androidx.appcompat:appcompat:1.6.1")
    implementation("androidx.core:core:1.12.0")
    implementation("androidx.constraintlayout:constraintlayout:2.1.4")
    implementation("androidx.recyclerview:recyclerview:1.3.2")
    implementation("com.google.android.material:material:1.11.0")

    // Google API client dependencies
    implementation("com.google.api-client:google-api-client:1.31.5")
    implementation("com.google.oauth-client:google-oauth-client-jetty:1.31.5")
    implementation("com.google.apis:google-api-services-photoslibrary:v1-rev1-1.23.0")
    implementation("org.tensorflow:tensorflow-lite:2.12.0")
    implementation("org.tensorflow:tensorflow-lite-gpu:2.12.0") // Optional for GPU acceleration

    // Retrofit for API calls
    implementation("com.squareup.retrofit2:retrofit:2.9.0")
    implementation("com.squareup.retrofit2:converter-gson:2.9.0")
    implementation("com.squareup.okhttp3:logging-interceptor:4.12.0")
    implementation("com.squareup.okhttp3:okhttp:4.12.0")

    // Gson for JSON parsing (only declared once now)
    implementation("com.google.code.gson:gson:2.10.1")

    // ML Kit dependencies (make sure you have the latest version for image labeling)
    implementation("com.google.mlkit:image-labeling:17.0.0")

    // AdMob for monetization
    implementation("com.google.android.gms:play-services-ads:22.6.0")

    // Google Play Billing
    implementation("com.android.billingclient:billing:6.1.0")

    // Play Services for App Updates
    implementation("com.google.android.play:app-update:2.1.0")
    implementation("com.google.android.play:app-update-ktx:2.1.0")
    
    // Jetpack DataStore for modern preferences
    implementation("androidx.datastore:datastore-preferences:1.0.0")

    // WorkManager for background tasks
    implementation("androidx.work:work-runtime:2.9.0")

    // Room database (optional, if you want to use SQL instead of JSON)
    implementation("androidx.room:room-runtime:2.6.1")
    annotationProcessor("androidx.room:room-compiler:2.6.1")

    // Fuzzy string matching
    implementation("me.xdrop:fuzzywuzzy:1.4.0")

    // Image loading and processing
    implementation("com.github.bumptech.glide:glide:4.16.0")
    annotationProcessor("com.github.bumptech.glide:compiler:4.16.0")

    // Lottie animations for tutorials
    implementation("com.airbnb.android:lottie:6.3.0")

    // Testing dependencies
    testImplementation("junit:junit:4.13.2")
    androidTestImplementation("androidx.test.ext:junit:1.1.5")
    androidTestImplementation("androidx.test.espresso:espresso-core:3.5.1")

    // Accessibility testing
    androidTestImplementation("androidx.test:rules:1.5.0")
    androidTestImplementation("androidx.test:runner:1.5.2")

    // For handling permissions
    implementation("androidx.activity:activity:1.8.2")
    implementation("androidx.fragment:fragment:1.6.2")
}
