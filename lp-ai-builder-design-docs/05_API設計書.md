# LP AI Builder API設計書

## 1. 文書概要

### 1-1. 目的

本書は、LP AI Builder におけるフロントエンド（React）とバックエンド（PHP）間のAPI仕様を定義することを目的とする。

本システムは制作ツール寄りのWebアプリであり、以下を実現するAPI群を対象とする。

- プロジェクトの作成・取得・更新・削除
- LP構成データの保存と復元
- AIによる構成生成・コピー生成・本文生成
- コード生成
- JSONおよびコードのエクスポート
- 将来的な履歴管理やテンプレート化への拡張

---

## 2. 基本方針

### 2-1. API方式

- 形式: REST API
- データ形式: JSON
- 通信: HTTPS
- 文字コード: UTF-8

### 2-2. ベースURL例

```text
/api
```

### 2-3. レスポンス共通形式

成功時:

```json
{
  "success": true,
  "data": {}
}
```

失敗時:

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "入力内容を確認してください",
    "details": {}
  }
}
```

### 2-4. ステータスコード方針

| HTTP | 意味                 |
| ---- | -------------------- |
| 200  | 成功                 |
| 201  | 作成成功             |
| 400  | 不正リクエスト       |
| 401  | 未認証               |
| 403  | 権限なし             |
| 404  | 対象なし             |
| 422  | バリデーションエラー |
| 500  | サーバーエラー       |

---

## 3. 認証方針

### 3-1. 初期方針

MVPでは以下のどちらかを想定する。

#### 案A

- 管理者のみ利用
- 認証なしまたは簡易セッション

#### 案B

- ログインユーザーごとにプロジェクトを保持
- セッション認証またはBearer Token

### 3-2. 将来方針

- JWT認証
- チーム共有対応
- 権限管理

---

## 4. API一覧

| 区分     | メソッド | エンドポイント             | 用途                 |
| -------- | -------- | -------------------------- | -------------------- |
| Projects | GET      | /projects                  | プロジェクト一覧取得 |
| Projects | POST     | /projects                  | プロジェクト新規作成 |
| Projects | GET      | /projects/{id}             | プロジェクト詳細取得 |
| Projects | PUT      | /projects/{id}             | プロジェクト更新     |
| Projects | DELETE   | /projects/{id}             | プロジェクト削除     |
| Projects | POST     | /projects/{id}/duplicate   | プロジェクト複製     |
| Projects | GET      | /projects/{id}/export/json | JSON出力             |
| Projects | GET      | /projects/{id}/export/code | コード出力取得       |
| AI       | POST     | /ai/generate-structure     | セクション構成生成   |
| AI       | POST     | /ai/generate-copy          | コピー生成           |
| AI       | POST     | /ai/generate-section       | セクション本文生成   |
| AI       | POST     | /ai/generate-faq           | FAQ生成              |
| AI       | POST     | /ai/generate-code          | コード生成           |
| Assets   | POST     | /assets/upload             | 画像アップロード     |
| Settings | GET      | /settings                  | 設定取得             |
| Settings | PUT      | /settings                  | 設定更新             |

---

## 5. Projects API

# 5-1. プロジェクト一覧取得

### エンドポイント

```text
GET /api/projects
```

### クエリ例

- `page`
- `limit`
- `keyword`
- `status`
- `sort`

### レスポンス例

```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 1,
        "projectName": "WABE LP案",
        "productName": "WP AI Blog Engine",
        "status": "draft",
        "updatedAt": "2026-03-24T11:30:00+09:00"
      }
    ],
    "pagination": {
      "page": 1,
      "limit": 20,
      "total": 1
    }
  }
}
```

---

# 5-2. プロジェクト新規作成

### エンドポイント

```text
POST /api/projects
```

### リクエスト例

```json
{
  "projectName": "WABE LP案",
  "basicInfo": {
    "productName": "WP AI Blog Engine",
    "serviceName": "WP AI Blog Engine",
    "summary": "WordPress向けAIブログ生成ツール",
    "target": "中小企業・個人ブロガー",
    "ctaText": "無料で試す",
    "ctaUrl": "https://example.com"
  },
  "design": {
    "mainColor": "#2563eb",
    "designTone": "modern"
  },
  "sections": [
    { "sectionKey": "hero", "enabled": true, "sortOrder": 1 },
    { "sectionKey": "features", "enabled": true, "sortOrder": 2 },
    { "sectionKey": "faq", "enabled": true, "sortOrder": 3 }
  ]
}
```

### レスポンス例

```json
{
  "success": true,
  "data": {
    "id": 1,
    "message": "プロジェクトを作成しました"
  }
}
```

### バリデーション

- projectName 必須
- basicInfo.productName 必須
- basicInfo.summary 必須

---

# 5-3. プロジェクト詳細取得

### エンドポイント

```text
GET /api/projects/{id}
```

### レスポンス例

```json
{
  "success": true,
  "data": {
    "id": 1,
    "projectName": "WABE LP案",
    "status": "draft",
    "basicInfo": {
      "productName": "WP AI Blog Engine",
      "summary": "WordPress向けAIブログ生成ツール"
    },
    "design": {
      "mainColor": "#2563eb",
      "designTone": "modern"
    },
    "sections": [],
    "featureItems": [],
    "faqItems": [],
    "pricingPlans": [],
    "testimonials": [],
    "links": {},
    "assets": {},
    "outputSettings": {},
    "meta": {
      "createdAt": "2026-03-24T10:00:00+09:00",
      "updatedAt": "2026-03-24T11:30:00+09:00"
    }
  }
}
```

---

# 5-4. プロジェクト更新

### エンドポイント

```text
PUT /api/projects/{id}
```

### リクエスト例

```json
{
  "projectName": "WABE LP案 修正版",
  "basicInfo": {
    "catchCopy": "AIで記事制作をもっと速く",
    "ctaText": "今すぐ試す"
  },
  "design": {
    "mainColor": "#1d4ed8"
  },
  "sections": [
    {
      "id": "sec_hero_001",
      "title": "AIで記事制作をもっと速く",
      "body": "更新後本文",
      "sortOrder": 1,
      "enabled": true
    }
  ],
  "featureItems": [],
  "faqItems": [],
  "pricingPlans": [],
  "testimonials": []
}
```

### レスポンス例

```json
{
  "success": true,
  "data": {
    "message": "更新しました",
    "updatedAt": "2026-03-24T12:00:00+09:00"
  }
}
```

### 更新方針

- 基本はプロジェクト全体更新
- 将来はセクション単位更新APIも追加可能

---

# 5-5. プロジェクト削除

### エンドポイント

```text
DELETE /api/projects/{id}
```

### レスポンス例

```json
{
  "success": true,
  "data": {
    "message": "削除しました"
  }
}
```

---

# 5-6. プロジェクト複製

### エンドポイント

```text
POST /api/projects/{id}/duplicate
```

### レスポンス例

```json
{
  "success": true,
  "data": {
    "newProjectId": 8,
    "message": "複製しました"
  }
}
```

---

# 5-7. JSONエクスポート

### エンドポイント

```text
GET /api/projects/{id}/export/json
```

### レスポンス例

```json
{
  "success": true,
  "data": {
    "project": {}
  }
}
```

---

# 5-8. コード出力取得

### エンドポイント

```text
GET /api/projects/{id}/export/code
```

### レスポンス例

```json
{
  "success": true,
  "data": {
    "files": [
      {
        "fileName": "src/pages/LpPage.jsx",
        "content": "export default function LpPage() {}"
      }
    ]
  }
}
```

---

## 6. AI API

# 6-1. セクション構成生成

### エンドポイント

```text
POST /api/ai/generate-structure
```

### リクエスト例

```json
{
  "basicInfo": {
    "productName": "WP AI Blog Engine",
    "summary": "WordPress向けAIブログ生成ツール",
    "target": "中小企業・個人ブロガー",
    "priceText": "月額980円〜"
  },
  "options": {
    "includePricing": true,
    "includeFaq": true,
    "tone": "professional"
  }
}
```

### レスポンス例

```json
{
  "success": true,
  "data": {
    "sections": [
      { "sectionKey": "hero", "label": "Hero", "sortOrder": 1 },
      { "sectionKey": "problem", "label": "Problem", "sortOrder": 2 },
      { "sectionKey": "features", "label": "Features", "sortOrder": 3 },
      { "sectionKey": "pricing", "label": "Pricing", "sortOrder": 4 },
      { "sectionKey": "faq", "label": "FAQ", "sortOrder": 5 },
      { "sectionKey": "cta", "label": "CTA", "sortOrder": 6 }
    ]
  }
}
```

---

# 6-2. コピー生成

### エンドポイント

```text
POST /api/ai/generate-copy
```

### リクエスト例

```json
{
  "type": "catchCopy",
  "basicInfo": {
    "productName": "WP AI Blog Engine",
    "summary": "WordPress向けAIブログ生成ツール",
    "target": "中小企業・個人ブロガー"
  },
  "options": {
    "tone": "modern",
    "length": "short",
    "count": 3,
    "ngWords": ["最強", "完全無料"]
  }
}
```

### レスポンス例

```json
{
  "success": true,
  "data": {
    "type": "catchCopy",
    "candidates": ["AIで記事制作をもっと速く", "ブログ運用を自動化する新しい選択", "WordPress記事制作をAIで効率化"]
  }
}
```

---

# 6-3. セクション本文生成

### エンドポイント

```text
POST /api/ai/generate-section
```

### リクエスト例

```json
{
  "sectionKey": "features",
  "basicInfo": {
    "productName": "WP AI Blog Engine",
    "summary": "WordPress向けAIブログ生成ツール"
  },
  "sectionContext": {
    "title": "選ばれる理由",
    "existingItems": ["記事タイトル自動生成", "見出し構成の提案", "アイキャッチ生成"]
  },
  "options": {
    "tone": "professional",
    "length": "medium"
  }
}
```

### レスポンス例

```json
{
  "success": true,
  "data": {
    "title": "選ばれる理由",
    "subtitle": "制作時間と継続運用の両方を支援",
    "body": "記事タイトル、見出し、本文、画像までを一連でサポートし、継続的なブログ運用を効率化します。"
  }
}
```

---

# 6-4. FAQ生成

### エンドポイント

```text
POST /api/ai/generate-faq
```

### リクエスト例

```json
{
  "basicInfo": {
    "productName": "WP AI Blog Engine",
    "summary": "WordPress向けAIブログ生成ツール"
  },
  "options": {
    "count": 5,
    "tone": "clear"
  }
}
```

### レスポンス例

```json
{
  "success": true,
  "data": {
    "items": [
      {
        "question": "初心者でも使えますか？",
        "answer": "はい。初期設定後は比較的シンプルに利用できます。"
      }
    ]
  }
}
```

---

# 6-5. コード生成

### エンドポイント

```text
POST /api/ai/generate-code
```

### リクエスト例

```json
{
  "projectId": 1,
  "project": {
    "basicInfo": {},
    "design": {},
    "sections": [],
    "featureItems": [],
    "faqItems": [],
    "pricingPlans": [],
    "testimonials": [],
    "outputSettings": {
      "outputFormat": "react",
      "styleFormat": "tailwind",
      "componentSplit": true,
      "separateDataFile": true
    }
  }
}
```

### レスポンス例

```json
{
  "success": true,
  "data": {
    "files": [
      {
        "fileName": "src/pages/LpPage.jsx",
        "content": "export default function LpPage() { return null; }"
      },
      {
        "fileName": "src/components/lp/HeroSection.jsx",
        "content": "export default function HeroSection() { return null; }"
      },
      {
        "fileName": "src/data/lpData.js",
        "content": "export const lpData = {};"
      }
    ],
    "version": 1
  }
}
```

### 補足

- 生成後はDBの `generated_codes` に保存可能
- 出力形式追加時は `outputFormat` で分岐

---

## 7. Assets API

# 7-1. 画像アップロード

### エンドポイント

```text
POST /api/assets/upload
```

### リクエスト形式

- `multipart/form-data`

### フィールド

- file
- projectId
- assetType

### レスポンス例

```json
{
  "success": true,
  "data": {
    "assetId": 10,
    "assetUid": "asset_logo_001",
    "url": "/uploads/logo.png",
    "alt": "サービスロゴ"
  }
}
```

### バリデーション

- 画像形式のみ許可
- サイズ上限設定

---

## 8. Settings API

# 8-1. 設定取得

### エンドポイント

```text
GET /api/settings
```

### レスポンス例

```json
{
  "success": true,
  "data": {
    "ai": {
      "defaultModel": "gpt-4.1",
      "defaultTone": "professional",
      "defaultLength": "medium"
    },
    "output": {
      "defaultFormat": "react",
      "styleFormat": "tailwind"
    },
    "ui": {
      "defaultPreviewDevice": "pc",
      "autoSaveInterval": 30
    }
  }
}
```

---

# 8-2. 設定更新

### エンドポイント

```text
PUT /api/settings
```

### リクエスト例

```json
{
  "ai": {
    "defaultModel": "gpt-4.1",
    "defaultTone": "modern",
    "defaultLength": "medium"
  },
  "output": {
    "defaultFormat": "react",
    "styleFormat": "tailwind"
  },
  "ui": {
    "defaultPreviewDevice": "sp",
    "autoSaveInterval": 60
  }
}
```

### レスポンス例

```json
{
  "success": true,
  "data": {
    "message": "設定を更新しました"
  }
}
```

---

## 9. エラーレスポンス設計

# 9-1. バリデーションエラー

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "入力内容に誤りがあります",
    "details": {
      "projectName": ["プロジェクト名は必須です"],
      "basicInfo.productName": ["商品名は必須です"]
    }
  }
}
```

# 9-2. 未認証

```json
{
  "success": false,
  "error": {
    "code": "UNAUTHORIZED",
    "message": "認証が必要です"
  }
}
```

# 9-3. サーバーエラー

```json
{
  "success": false,
  "error": {
    "code": "INTERNAL_SERVER_ERROR",
    "message": "サーバー内部でエラーが発生しました"
  }
}
```

---

## 10. フロント実装向け補足

### 10-1. React側の呼び出し単位

- 一覧系: `useEffect` で取得
- 編集系: 入力後に保存ボタンまたは自動保存
- AI系: ボタン押下時のみ実行
- コード生成: 明示アクションで実行

### 10-2. 保存タイミング

- 基本は明示保存
- 将来的にオートセーブ追加
- オートセーブ時は `PUT /projects/{id}` を使う

### 10-3. データ整合性

- フロントで持つ統合オブジェクトをそのまま更新APIへ送る
- バックエンドで各テーブルに分配保存する

---

## 11. 将来拡張API候補

- `POST /api/projects/{id}/versions`
- `GET /api/projects/{id}/versions`
- `POST /api/projects/{id}/restore-version`
- `GET /api/templates`
- `POST /api/projects/{id}/apply-template`
- `POST /api/ai/generate-ab-patterns`
- `POST /api/ai/generate-image`
- `GET /api/exports/{id}/download`

---

## 12. 実装優先順位

### Phase 1

- GET /projects
- POST /projects
- GET /projects/{id}
- PUT /projects/{id}
- DELETE /projects/{id}
- POST /ai/generate-copy
- POST /ai/generate-section
- POST /ai/generate-code

### Phase 2

- POST /ai/generate-structure
- POST /ai/generate-faq
- POST /projects/{id}/duplicate
- GET /projects/{id}/export/json
- GET /projects/{id}/export/code
- POST /assets/upload

### Phase 3

- 設定API
- バージョン管理API
- テンプレートAPI

---

## 13. 結論

LP AI Builder のAPIは、**制作データの保存・AI生成・コード出力**の3本柱を中心に設計する。

特に重要なのは以下。

- フロントでは統合オブジェクトを扱いやすくする
- バックでは保存処理を分離して管理する
- AI系APIは用途別に分割する
- 将来の履歴管理やテンプレート機能を見越して拡張しやすくする

この設計で進めれば、React + PHP 構成でも責務が明確で、実装と保守の両方を進めやすいAPI基盤となる。
