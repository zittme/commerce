# Commerce (커머스)

[Zittme](https://github.com/zittme/zittme) 엔진용 쇼핑몰 모듈입니다. 상품·옵션·재고·장바구니·주문·배송·취소반품까지 운영 전반을 다루고, 결제는 [짓미페이](https://github.com/zittme/zittme_pay)가 처리합니다.

## 요구 사항

- Zittme 0.0.01 이상
- [zittme_pay](https://github.com/zittme/zittme_pay) 0.2.0 이상 (결제 계층)

## 설치

Zittme 설치 경로의 `modules/commerce` 에 이 저장소의 내용을 놓습니다.

```bash
cd 설치경로/modules
git clone https://github.com/zittme/commerce.git
```

압축 파일로 받았다면 `modules/commerce/` 에 풀면 됩니다. 이후 관리자 화면에 접속하면 테이블 생성과 기본 설정이 자동으로 진행됩니다. 업데이트 후에는 설치된 모듈 목록에서 모듈 업데이트를 실행해 주세요.

## 주요 기능

- 상품·카테고리·재고 관리, 진열 순서 드래그 정렬, 상품 뱃지
- 조합형 옵션: 색상 × 사이즈처럼 축을 정해 한 번에 생성
- 장바구니·주문·배송 관리, 전용 콘솔에서 주문 처리와 거래명세서 인쇄, 택배사 업로드용 CSV
- 쿠폰·적립금(자체 원장)·회원 등급 할인
- 해외 배송지 입력 (국가·도시·주/도·국가번호, 지역별 추가 배송비)
- 다통화 판매
  - 판매 통화(USD, JPY 등)를 추가하면 상품별 통화 가격 등록, 표시 통화 전환, 외화 주문까지 처리됩니다
  - 주문에는 결제 시점 환율이 저장되어 이후 환율 변동과 무관합니다
  - 주문서·주문 내역·관리자 화면·거래명세서가 주문 통화로 표기되고, 매출 통계는 KRW 환산 기준을 유지합니다
  - 쿠폰과 적립금은 KRW 주문 전용입니다. 다통화를 쓰지 않는 사이트는 기존과 동일하게 동작합니다
- 매출 통계 (기간·상품·지역별), 리뷰·상품 문의, 기획전
- 12개 언어 지원

## 구조 원칙

- 주문 금액은 항상 서버가 재계산합니다. 재고는 조건부 UPDATE 원자 경로로만 차감합니다.
- 주문은 3계층입니다: `commerce_order`(결제 단위) → `commerce_order_seller`(판매자별) → `commerce_order_item`(스냅샷).
- 결제는 zittme_pay 에 위임합니다 (의존 방향: commerce → zittme_pay 단방향).

## 라이선스

[GPL v2](LICENSE)

## 문의

- 홈페이지: https://zitt.me
- 매뉴얼: https://zitt.me/manual
