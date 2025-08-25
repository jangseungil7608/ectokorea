export default {
  // 공통
  common: {
    loading: '처리 중...',
    success: '성공',
    error: '오류',
    save: '저장',
    cancel: '취소',
    delete: '삭제',
    edit: '수정',
    add: '추가',
    search: '검색',
    reset: '초기화',
    confirm: '확인',
    close: '닫기',
    back: '뒤로',
    next: '다음',
    yes: '예',
    no: '아니오'
  },

  // 헤더
  header: {
    title: '🇯🇵🇰🇷 일본→한국 EC 출품 도구',
    subtitle: '일본 상품을 한국 쿠팡에 효율적으로 출품하기 위한 도구입니다.'
  },

  // 네비게이션
  nav: {
    calculator: '💰 이익 계산기',
    products: '📦 상품 관리'
  },

  // 인증
  auth: {
    login: '로그인',
    logout: '로그아웃',
    register: '회원가입',
    email: '이메일',
    password: '비밀번호',
    passwordConfirm: '비밀번호 확인',
    name: '이름',
    loginRequired: '로그인이 필요합니다',
    loginSuccess: '로그인 성공',
    logoutSuccess: '로그아웃 성공',
    registerSuccess: '회원가입 성공',
    invalidCredentials: '이메일 또는 비밀번호가 올바르지 않습니다',
    passwordMismatch: '비밀번호가 일치하지 않습니다',
    agreeTerms: '이용약관에 동의합니다',
    termsRequired: '이용약관에 동의해야 합니다'
  },

  // 이익 계산기
  calculator: {
    title: '일본 → 한국 이익 계산기',
    productInfo: '일본 상품 정보',
    productPrice: '상품 가격 (JPY)',
    productWeight: '상품 무게 (g)',
    category: '상품 카테고리',
    
    costs: '비용 설정',
    domesticShipping: '일본 배송비 (JPY)',
    internationalShipping: '국제 배송비',
    shippingMethod: '배송 방법',
    airShipping: '항공배송 (빠름, 비쌈)',
    seaShipping: '해상배송 (느림, 저렴)',
    packaging: '포장비 (원)',
    
    selling: '한국 판매 정보',
    sellingPrice: '쿠팡 판매가 (KRW)',
    koreaShipping: '한국 배송비 (KRW)',
    packaging: '포장비 (KRW)',
    targetProfit: '목표 이익률 (%)',
    recommendedPrice: '추천 판매가',
    
    results: '📊 계산 결과',
    totalCost: '총 비용',
    profit: '순이익',
    netProfit: '순 이익',
    profitRate: '이익률',
    breakdown: '비용 상세',
    detailedAnalysis: '상세비용분석',
    customs: '관세',
    vat: '부가세',
    platformFee: '플랫폼 수수료',
    japanProductShipping: '일본 상품가 + 배송비',
    internationalShipping: '국제배송비',
    totalJPYCost: '총 JPY 비용',
    krwConversion: 'KRW 환산 금액',
    taxExempt: '면세 적용',
    recommendedSellingPrice: '💡 추천 판매가',
    detailedCostAnalysis: '상세 비용 분석',
    taxInfo: '세금 정보',
    
    calculate: '💰 이익 계산하기',
    calculateRecommended: '추천 판매가 계산',
    
    categoryInfo: '카테고리에 따라 관세율과 수수료가 달라집니다',
    koreaShippingPackaging: '한국 배송 + 포장비',
    customsVat: '관세 + 부가세',
    customsVatDetail: '관세 {0}% + 부가세 10%',
    targetProfitRecommend: '목표 이익률 {0}%를 위한 추천 가격',
    actualProfitRate: '실제 이익률: {0}%',
    
    categories: {
      general: '기타/일반',
      cosmetics: '화장품',
      fashion: '의류/패션',
      electronics: '전자제품',
      food: '식품',
      books: '도서'
    },
    
    taxExemptReason: '개인용품 면세 기준 이하'
  },

  // 상품 관리
  products: {
    title: '상품 관리',
    addProduct: 'Amazon 상품 등록',
    productList: '상품 목록',
    asinCode: 'ASIN',
    productName: '상품명',
    price: '가격',
    image: '상품 이미지 URL',
    url: 'Amazon URL',
    actions: '작업',
    noProducts: '등록된 상품이 없습니다',
    fetchingInfo: '상품 정보를 가져오는 중...',
    registerProduct: '상품 등록하기',
    deleteConfirm: '이 상품을 삭제하시겠습니까?',
    registerSuccess: '상품이 성공적으로 등록되었습니다',
    deleteSuccess: '상품이 삭제되었습니다',
    fetchError: '상품 정보를 가져오는데 실패했습니다',
    registerError: '상품 등록에 실패했습니다',
    deleteError: '상품 삭제에 실패했습니다',
    asinFetch: 'ASIN으로 상품 정보 가져오기',
    testAsin: '테스트 ASIN: B08N5WRWNW (iPad Air), B087QQNGQR (Nintendo Switch), B09V4772QK (Sony 헤드폰)',
    fetchSuccess: '상품 정보를 성공적으로 가져왔습니다!',
    fetchFailed: '상품 정보를 가져올 수 없습니다',
    nameRequired: '상품명을 입력해주세요.',
    registerSuccessMsg: '상품을 성공적으로 등록했습니다!',
    registerFailedMsg: '상품 등록에 실패했습니다'
  },

  // 상품 상세
  product: {
    description: '상품 설명',
    noDescription: '상품 설명이 없습니다.',
    additionalImages: '추가 이미지',
    features: '주요 특징',
    specifications: '상품 사양',
    gallery: '이미지 갤러리',
    mainImage: '메인 이미지',
    thumbnails: '썸네일',
    viewLargeImage: '큰 이미지 보기',
    imageError: '이미지를 불러올 수 없습니다',
    noImages: '이미지가 없습니다'
  },

  // 테마
  theme: {
    light: '라이트 모드',
    dark: '다크 모드',
    system: '시스템 설정',
    toggle: '테마 전환'
  },

  // 언어
  language: {
    korean: '한국어',
    japanese: '日本語',
    toggle: '언어 전환'
  },

  // 폼 유효성 검사
  validation: {
    required: '필수 입력 항목입니다',
    email: '올바른 이메일 형식이 아닙니다',
    minLength: '최소 {min}자 이상 입력해주세요',
    maxLength: '최대 {max}자까지 입력 가능합니다',
    number: '숫자만 입력 가능합니다',
    positive: '0보다 큰 값을 입력해주세요'
  },

  // 환율
  exchange: {
    currentRate: '현재 환율',
    updateRate: '환율 업데이트',
    lastUpdate: '마지막 업데이트'
  }
}