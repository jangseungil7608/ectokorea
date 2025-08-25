export default {
  // 共通
  common: {
    loading: '処理中...',
    success: '成功',
    error: 'エラー',
    save: '保存',
    cancel: 'キャンセル',
    delete: '削除',
    edit: '編集',
    add: '追加',
    search: '検索',
    reset: 'リセット',
    confirm: '確認',
    close: '閉じる',
    back: '戻る',
    next: '次へ',
    yes: 'はい',
    no: 'いいえ'
  },

  // ヘッダー
  header: {
    title: '🇯🇵🇰🇷 日本→韓国 EC出品ツール',
    subtitle: '日本商品を韓国クパンに効率的に出品するためのツールです。'
  },

  // ナビゲーション
  nav: {
    calculator: '💰 利益計算機',
    products: '📦 商品管理'
  },

  // 認証
  auth: {
    login: 'ログイン',
    logout: 'ログアウト',
    register: '会員登録',
    email: 'メールアドレス',
    password: 'パスワード',
    passwordConfirm: 'パスワード確認',
    name: '名前',
    loginRequired: 'ログインが必要です',
    loginSuccess: 'ログイン成功',
    logoutSuccess: 'ログアウト成功',
    registerSuccess: '会員登録成功',
    invalidCredentials: 'メールアドレスまたはパスワードが正しくありません',
    passwordMismatch: 'パスワードが一致しません',
    agreeTerms: '利用規約に同意します',
    termsRequired: '利用規約への同意が必要です'
  },

  // 利益計算機
  calculator: {
    title: '日本 → 韓国 利益計算機',
    productInfo: '日本商品情報',
    productPrice: '商品価格 (JPY)',
    productWeight: '商品重量 (g)',
    category: '商品カテゴリ',
    
    costs: '費用設定',
    domesticShipping: '日本配送料 (JPY)',
    internationalShipping: '国際送料',
    shippingMethod: '配送方法',
    airShipping: '航空便 (速い、高い)',
    seaShipping: '船便 (遅い、安い)',
    packaging: '梱包費 (원)',
    
    selling: '韓国販売情報',
    sellingPrice: 'クパン販売価格 (KRW)',
    koreaShipping: '韓国配送料 (KRW)',
    packaging: '梱包費 (KRW)',
    targetProfit: '目標利益率 (%)',
    recommendedPrice: '推奨販売価格',
    
    results: '📊 計算結果',
    totalCost: '総費用',
    profit: '純利益',
    netProfit: '純 利益',
    profitRate: '利益率',
    breakdown: '費用詳細',
    detailedAnalysis: '詳細費用分析',
    customs: '関税',
    vat: '付加価値税',
    platformFee: 'プラットフォーム手数料',
    japanProductShipping: '日本商品価格 + 送料',
    internationalShipping: '国際送料',
    totalJPYCost: '総JPY費用',
    krwConversion: 'KRW換算金額',
    taxExempt: '免税適用',
    recommendedSellingPrice: '💡 推奨販売価格',
    detailedCostAnalysis: '詳細費用分析',
    taxInfo: '税金情報',
    
    calculate: '💰 利益を計算する',
    calculateRecommended: '推奨販売価格を計算',
    
    categoryInfo: 'カテゴリによって関税率と手数料が異なります',
    koreaShippingPackaging: '韓国配送 + 梱包費',
    customsVat: '関税 + 付加価値税',
    customsVatDetail: '関税 {0}% + 付加価値税 10%',
    targetProfitRecommend: '目標利益率 {0}%のための推奨価格',
    actualProfitRate: '実際利益率: {0}%',
    
    categories: {
      general: 'その他/一般',
      cosmetics: '化粧品',
      fashion: '衣類/ファッション',
      electronics: '電子製品',
      food: '食品',
      books: '書籍'
    },
    
    taxExemptReason: '個人用品免税基準以下'
  },

  // 商品管理
  products: {
    title: '商品管理',
    addProduct: 'Amazon商品登録',
    productList: '商品一覧',
    asinCode: 'ASIN',
    productName: '商品名',
    price: '価格',
    image: '商品画像URL',
    url: 'Amazon URL',
    actions: '操作',
    noProducts: '登録された商品がありません',
    fetchingInfo: '商品情報を取得中...',
    registerProduct: '商品登録する',
    deleteConfirm: 'この商品を削除しますか？',
    registerSuccess: '商品が正常に登録されました',
    deleteSuccess: '商品が削除されました',
    fetchError: '商品情報の取得に失敗しました',
    registerError: '商品登録に失敗しました',
    deleteError: '商品削除に失敗しました',
    asinFetch: 'ASINで商品情報を取得',
    testAsin: 'テストASIN: B08N5WRWNW (iPad Air), B087QQNGQR (Nintendo Switch), B09V4772QK (Sony ヘッドフォン)',
    fetchSuccess: '商品情報を正常に取得しました！',
    fetchFailed: '商品情報を取得できません',
    nameRequired: '商品名を入力してください。',
    registerSuccessMsg: '商品を正常に登録しました！',
    registerFailedMsg: '商品登録に失敗しました'
  },

  // テーマ
  theme: {
    light: 'ライトモード',
    dark: 'ダークモード',
    system: 'システム設定',
    toggle: 'テーマ切り替え'
  },

  // 言語
  language: {
    korean: '한국어',
    japanese: '日本語',
    toggle: '言語切り替え'
  },

  // フォームバリデーション
  validation: {
    required: '必須入力項目です',
    email: '正しいメール形式ではありません',
    minLength: '最低{min}文字以上入力してください',
    maxLength: '最大{max}文字まで入力可能です',
    number: '数字のみ入力可能です',
    positive: '0より大きい値を入力してください'
  },

  // 為替
  exchange: {
    currentRate: '現在の為替レート',
    updateRate: '為替レート更新',
    lastUpdate: '最終更新'
  }
}