# Zittme Commerce

[Zittme](https://github.com/zittme/zittme) 엔진용 커머스(쇼핑몰) 모듈입니다. 상품·카테고리, 장바구니·주문, 쿠폰·적립금, 회원 등급, 재고 관리와 전용 운영 콘솔을 제공합니다.

## 요구 사항

- Zittme 0.0.01 이상
- 결제 기능을 쓰려면 [zittme-pay](https://github.com/zittme/zittme_pay) 모듈이 필요합니다. 없어도 커머스 자체는 동작하며, 결제 기능만 비활성화됩니다.

## 설치

Zittme 설치 경로의 `modules/commerce` 에 이 저장소의 내용을 놓습니다.

```bash
cd 설치경로/modules
git clone https://github.com/zittme/commerce.git commerce
```

압축 파일로 받았다면 `modules/commerce/` 에 풀면 됩니다. 이후 관리자 화면에 접속하면 테이블 생성과 기본 설정이 자동으로 진행됩니다.

## 주요 기능

- 상품·옵션·카테고리·태그, 재고 관리
- 장바구니, 주문·클레임(취소·교환·반품) 처리
- 쿠폰, 적립금(자체 원장), 회원 등급
- 배송비 정책
- 판매자 구조 (오픈마켓 확장 대비)
- 관리자 대시보드·통계와 별도 운영 콘솔
- 스킨 방식의 프론트 화면 (기본 스킨 포함)

## 라이선스

[GPL v2](LICENSE)

## 문의

- 홈페이지: https://zitt.me
- 매뉴얼: https://zitt.me/manual
