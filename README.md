# EctoKorea - 日韓EC商品収益性計算ツール

日本Amazon、楽天、JINSから商品を収集し、韓国Coupang販売時の収益性を計算・管理するWebアプリケーションです。

## 🚀 主要機能

### ✅ 実装済み機能
- **💰 収益計算機**: 日本購入価格から韓国販売までの全コストと利益を正確に計算
- **📊 リアルタイム為替**: 韓国輸出入銀行APIによるリアルタイムJPY-KRW為替連携
- **🏷️ カテゴリー別関税**: 化粧品、アパレル、電子製品等カテゴリー別正確な関税率適用
- **🚚 配送費計算**: 航空・海上配送選択と重量別自動配送費計算
- **🎯 目標利益率**: 希望利益率基準の推奨販売価格自動計算
- **🔍 多サイト商品収集**: Amazon、楽天、JINS商品情報自動収集 (Python FastAPI)
- **🖼️ 画像ギャラリー**: Amazon風6枚画像ギャラリー対応
- **📝 商品管理**: 収集商品の登録・修正・削除・照会
- **⚡ 大量収集システム**: Laravel Queue基盤のバックグラウンド処理
- **📈 収集作業モニタリング**: リアルタイム進行状況・統計・結果追跡
- **🔗 URL収集対応**: Amazon ベストセラーページ等URL直接収集
- **🔐 JWT認証システム**: セキュアなユーザー認証 (実装予定)

### 🔄 実装予定機能
- 楽天・JINS商品スクレイピング完成
- 自動翻訳 (日本語 → 韓国語)
- Coupang商品登録支援
- 在庫管理システム

## 💻 技術スタック

- **バックエンド**: Laravel 12 + PHP 8.2+
- **フロントエンド**: Vue 3 + Vite + Tailwind CSS  
- **データベース**: PostgreSQL
- **Pythonスクレイパー**: FastAPI + httpx + BeautifulSoup4 + trafilatura
- **為替API**: 韓国輸出入銀行 Open API
- **インフラ**: Docker Compose

## 📋 インストール・実行

### 1. 環境設定

```bash
# バックエンド環境変数設定
cd backend
cp .env.example .env
```

`.env`ファイルで以下の設定を変更してください:
```env
# 韓国輸出入銀行為替APIキー (必須)
KOREA_EXIMBANK_API_KEY=発行されたキーを入力

# データベース設定 (Docker使用時)
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=coupang
DB_USERNAME=youruser
DB_PASSWORD=yourpass

# Pythonスクレイパー設定
PYTHON_SCRAPER_URL=http://192.168.1.13:8001/ectokorea/api/v1
```

### 2. Docker実行 (推奨)

```bash
# 全サービス開始
docker-compose up -d

# サービス確認
docker-compose ps
```

### 3. 手動実行

**バックエンド:**
```bash
cd backend
composer install
php artisan key:generate
php artisan migrate
php artisan serve --host=0.0.0.0 --port=8080
```

**フロントエンド:**
```bash
cd frontend
npm install
npm run dev
```

**Pythonスクレイパー:**
```bash
cd python-scraper
pip install -r requirements.txt
uvicorn main:app --host 0.0.0.0 --port 8001 --reload
```

## 🌐 アクセスURL

### ローカル接続
- **フロントエンド**: `http://192.168.1.13:5173/ectokorea`
- **バックエンドAPI**: `http://192.168.1.13:8080/ectokorea/*`
- **PythonスクレイパーAPI**: `http://192.168.1.13:8001/ectokorea/api/v1/*`

### 外部接続
- **フロントエンド**: `https://devseungil.synology.me/ectokorea`
- **バックエンドAPI**: `https://devseungil.mydns.jp/ectokorea/*`
- **PythonスクレイパーAPI**: `https://devseungil.mydns.jp:8001/ectokorea/api/v1/*`
- **PostgreSQL**: `localhost:55432`

## 📖 使用方法

### 商品収集機能

1. **Amazon商品収集**
   - ASIN入力で直接収集: `http://192.168.1.13:8001/ectokorea/api/v1/scrape/amazon?asin=B0DJNXJTJL`
   - URL自動検出: `http://192.168.1.13:8001/ectokorea/api/v1/scrape?url=https://www.amazon.co.jp/dp/B0DJNXJTJL`
   - 6枚画像ギャラリー、商品説明、特徴を自動収集

2. **楽天商品収集** (実装予定)
   - Shop ID + Item Code による収集
   - URL自動検出対応

3. **JINS商品収集** (実装予定)
   - Product ID による収集
   - URL自動検出対応

### 収益計算機の使用

1. **日本商品情報入力**
   - 商品価格 (JPY)
   - 日本配送費
   - 商品重量
   - 配送方法 (航空・海上)
   - 商品カテゴリー

2. **韓国販売情報入力**
   - Coupang販売価格 (KRW)
   - 韓国配送費
   - 梱包費 (オプション)

3. **結果確認**
   - 総コスト分析
   - 純利益計算
   - 利益率表示
   - 税金・手数料詳細内訳

### 推奨販売価格計算

1. 目標利益率設定 (5-50%)
2. 「推奨販売価格計算」ボタンクリック
3. 目標利益率達成のための適正販売価格確認

## 🔑 為替APIキー発行

1. [韓国輸出入銀行為替API](https://www.koreaexim.go.kr/site/program/financial/exchangeJSON)にアクセス
2. 簡単な本人認証後APIキー発行
3. `.env`ファイルの`KOREA_EXIMBANK_API_KEY`に入力

## 📊 計算ロジック

### 総コスト計算
```
総コスト = 日本購入価格 + 日本配送費 + 国際配送費 + 関税 + 付加価値税 + 韓国配送費 + プラットフォーム手数料
```

### 税金計算
- **関税**: カテゴリー別 (0-30%)
  - 一般: 8%
  - 化粧品: 8%
  - アパレル: 13%
  - 電子製品: 8%
  - 食品: 30%
  - 書籍: 0%
- **付加価値税**: (購入価格 + 関税) × 10%
- **免税**: $150以下個人用品

### 国際配送費
- **航空便**: ¥1,000/kg
- **海上便**: ¥300/kg

### プラットフォーム手数料 (Coupang)
- 化粧品: 15%
- アパレル: 12%
- 電子製品: 10%
- 食品: 18%
- 書籍: 10%
- その他: 12%

## 🛠️ 開発環境設定

```bash
# バックエンドテスト
cd backend
php artisan test

# フロントエンドビルド
cd frontend
npm run build

# Pythonスクレイパーテスト
cd python-scraper
python -m pytest tests/

# Laravel Queue関連コマンド
php artisan queue:work --stop-when-empty  # Queue Worker実行
php artisan queue:monitor database         # Queue作業モニタリング
php artisan queue:failed                   # 失敗作業確認
php artisan schedule:run                   # Laravel Scheduler実行（推奨）
```

## 📡 APIエンドポイント

### 商品管理
- `GET /ectokorea/collected-products` - 商品一覧照会
- `POST /ectokorea/collected-products/collect` - 商品収集リクエスト
- `GET /ectokorea/collected-products/{id}` - 商品詳細照会
- `DELETE /ectokorea/collected-products/{id}` - 商品削除

### Pythonスクレイピングサービス
- `GET /ectokorea/api/v1/scrape/amazon?asin={asin}` - Amazon商品スクレイピング
- `GET /ectokorea/api/v1/scrape?url={product_url}` - URL自動検出スクレイピング
- `GET /ectokorea/api/v1/sites` - サポートサイト一覧

## ⚠️ 注意事項

1. **法的遵守**: Amazon利用規約を遵守し、直接的なデータ抽出は行いません
2. **個人使用**: 商業的転売ではなく、個人輸入・販売目的で設計されています
3. **正確性**: 計算結果は参考用であり、実際の税金・手数料は変動する可能性があります
4. **スクレイピング**: robots.txtを尊重し、適切な間隔でリクエストを送信します

## 🏗️ アーキテクチャ

### 推奨構成 (内部ネットワーク)
```
ユーザー → フロントエンド → バックエンド → Pythonスクレイパー
   ↓           ↓          ↓           ↓
192.168.1.13:5173 → :8080 → :8001 (内部専用)
```

### 混合構成 (現在設定)
```
フロントエンド(外部) → バックエンド(外部) → Pythonスクレイパー(内部)
       ↓               ↓                ↓
devseungil.synology.me → devseungil.mydns.jp → 192.168.1.13:8001
```

## 🤝 コントリビューション

バグレポートや機能提案はIssuesでお知らせください。

## 📄 ライセンス

個人および教育目的で自由に使用可能です。