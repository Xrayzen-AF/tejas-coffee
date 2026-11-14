### 5. S4-05: SPESIFIKASI DOKUMENTASI API (POSTMAN / OPENAPI)

Untuk kelompok yang mengimplementasikan arsitektur REST API atau decoupled, ekspor Postman Collection disimpan pada berkas `docs/04-api/tejas_coffee_api_collection.json`. Spesifikasi OpenAPI lengkap tersimpan di `docs/04-api/openapi.json`, dan kontrak data rinci ada di `docs/04-api/API_CONTRACT.md`.

Autentikasi memakai **sesi login Laravel** (cookie `laravel_session`), bukan Bearer token, karena aplikasi ini monolitik Blade + API internal. Harga dan stok selalu dihitung ulang di server.

#### Potongan Spesifikasi Endpoint Transaksi Inti (OpenAPI JSON Format):

```json
{
  "openapi": "3.0.0",
  "info": {
    "title": "Tejas Coffee POS Core Order API",
    "version": "1.0.0-rc",
    "description": "Spesifikasi resmi endpoint transaksi pesanan coffee shop UKK RPL 2026/2027"
  },
  "paths": {
    "/api/orders": {
      "post": {
        "summary": "Buat Pesanan Baru (Anti-Oversell Stok)",
        "security": [{ "sessionAuth": [] }],
        "requestBody": {
          "required": true,
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "required": ["tipe_pesanan", "items"],
                "properties": {
                  "id_meja": { "type": "integer", "example": 4 },
                  "tipe_pesanan": { "type": "string", "example": "dine-in" },
                  "id_promo": { "type": "integer", "example": 1 },
                  "tukar_poin": { "type": "boolean", "example": true },
                  "items": {
                    "type": "array",
                    "items": {
                      "type": "object",
                      "properties": {
                        "id_produk": { "type": "integer", "example": 3 },
                        "jumlah": { "type": "integer", "example": 2 },
                        "catatan_item": { "type": "string", "example": "Less sugar" }
                      }
                    }
                  }
                }
              }
            }
          }
        },
        "responses": {
          "201": {
            "description": "Pesanan Berhasil Dibuat (Status BARU)",
            "content": {
              "application/json": {
                "example": {
                  "message": "Pesanan berhasil dibuat.",
                  "data": {
                    "nomor_pesanan": "PSN-20261114-A1B2C3",
                    "status_pesanan": "baru",
                    "total_harga": "71800.00",
                    "diskon": "2000.00",
                    "poin_dipakai": 100,
                    "transaksi": { "status_pembayaran": "menunggu" }
                  }
                }
              }
            }
          },
          "422": {
            "description": "Validasi Gagal atau Stok Tidak Mencukupi (Anti-Oversell)",
            "content": {
              "application/json": {
                "example": {
                  "message": "Stok Cappuccino tidak mencukupi (sisa 1)."
                }
              }
            }
          }
        }
      }
    }
  }
}
```
