FROM node:20-alpine

# 作業ディレクトリを作成
WORKDIR /app

# 依存関係だけ先にコピー（キャッシュを効かせるため）
COPY package*.json ./

# 依存ライブラリをインストール
RUN npm install

# アプリケーション本体をコピー
COPY . .

# Vite プラグイン（開発時のみ）を明示的に入れる必要があるなら
# RUN npm install -D @vitejs/plugin-vue

# ポート公開（Viteのデフォルト）
EXPOSE 5173

# 開発サーバー起動（ネットワークアクセス有効化）
CMD ["npm", "run", "dev", "--", "--host"]
