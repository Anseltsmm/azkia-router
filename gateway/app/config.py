from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    app_name: str = "Azkia Router"
    env: str = "production"
    api_host: str = "127.0.0.1"
    api_port: int = 8001
    database_url: str
    redis_url: str = "redis://127.0.0.1:6379/0"
    default_upstream_base_url: str
    default_upstream_api_key: str
    # APP_KEY dari dashboard Laravel (format base64:...) — dipakai untuk mendekripsi
    # providers.api_key_encrypted yang disimpan terenkripsi AES-256-CBC + HMAC oleh Laravel.
    laravel_app_key: str = ""
    admin_bootstrap_key: str
    # Token opsional untuk /health/models (dikirim dashboard via header X-Health-Token).
    # Kosong = endpoint terbuka (seperti /health).
    health_token: str = ""
    cache_enabled: bool = True
    cache_ttl_seconds: int = 60
    default_generation_max_tokens: int = 4096
    max_generation_tokens: int = 32768
    cors_origins: list[str] = ["*"]

    model_config = SettingsConfigDict(env_prefix="AZKIA_", env_file=".env", extra="ignore")


settings = Settings()
