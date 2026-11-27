# Certify LMS

マルチ資格対応の資格学習プラットフォームです。受講生は資格ごとの教材で学習し、演習問題・模擬試験で理解度を確かめながら、コーチの面談サポートを受けて資格取得を目指せます。

> プロジェクト構造・ドメインモデル・コードの読み進め方は [ONBOARDING.md](./ONBOARDING.md) を参照してください。

## 作成者

 高橋秀和

## 主な機能

| ロール | 機能 |
|---|---|
| 受講生（student） | 教材閲覧 / 演習問題・苦手分野ドリル / 模擬試験（分野別ヒートマップ・合格可能性スコア）/ 面談予約 / チャット / 学習時間・進捗・ストリーク管理 / 修了証の受領 |
| コーチ（coach） | 教材・演習問題・模試の管理 / 担当受講生の進捗フォロー / 面談対応・面談メモ / チャット |
| 管理者（admin） | ユーザー招待・管理 / 資格・資格分類マスタ管理 / 資格へのコーチ割当 / 面談回数の付与 / 全体ダッシュボード |

このほか、以下の3つの外部サービス連携機能があります。いずれも `.env.example` の時点ではダミーのAPIキーが入っており、そのままでもアプリは起動しますが、各機能は「未連携時のフォールバック動作」になります。実際のAPIキーへの切り替え手順・動作確認手順は、本READMEの「外部サービス連携のセットアップ・動作確認」章（環境構築手順のあとに続く章）にすべて直接記載しています。

- **Google Calendar 連携**: コーチの面談予約にGoogleカレンダーの予定（Busy時間）を反映し、予約確定・キャンセル時にGoogleカレンダー側にもイベントを作成・削除します。未連携の場合はFreeBusy反映なしの従来動作にフォールバックします。
- **Gemini AI チャットボット**: 受講生が閲覧中の教材（Section）の内容を踏まえて、AIに質問・相談できます。`AI_CHAT_ENABLED=false` で機能自体を無効化できます。
- **Stripe 連携**: 受講生が追加の面談パックをStripe Checkoutで購入し、Webhook経由で決済結果（成功・失敗・期限切れ）が反映されます。

## 動作環境

- Docker Desktop / Docker Compose
- 開発環境は Laravel Sail で構築します（PHP コンテナ・MySQL・Mailpit・phpMyAdmin を起動）

※ Windows の場合は WSL2 の利用を推奨します。

## 環境構築手順

### 1. リポジトリの clone

```bash
git clone https://github.com/cactus777cheshire55/Certify-LMS
cd Certify-LMS
```

### 2. 環境変数ファイルの作成

```bash
cp .env.example .env
```

`.env.example` は Sail 向けに設定済みのため、コピーするだけでローカル開発を始められます。Google Calendar / Gemini / Stripe の3つの外部連携キーは**ダミー値**（`your_google_client_secret_here` 等）で初期化されているため、そのままでも起動・ログインは可能ですが、各連携機能自体は動作しません。実際にAPIキーを実データに置き換えて動かす手順は、本README後半の「外部サービス連携のセットアップ・動作確認」章（Google Calendar 連携 / Gemini AI チャットボット / Stripe 連携 それぞれについて、APIキーの取得手順・`.env`書き換え・画面操作による動作確認・DBチェックポイントまで）に直接記載しています。

### 3. 依存パッケージのインストール（初回のみ）

`vendor/` がまだ無いため、初回のみ Docker 経由で Composer を実行します。

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs
```

### 4. Sail エイリアスの設定（推奨）

```bash
alias sail='./vendor/bin/sail'
```

以降のコマンドはこのエイリアス前提で記載します（未設定の場合は `./vendor/bin/sail` に読み替えてください）。

### 5. コンテナの起動

```bash
sail up -d
```

`compose.yaml` には、Web アプリ本体（`laravel.test`）に加えて、通知・メール送信などのキュー済みジョブを処理する `queue`（`php artisan queue:work` 常駐）コンテナも定義されています。`sail up -d` で両方が起動します。

### 6. アプリケーションの初期化

```bash
sail artisan key:generate
sail artisan storage:link
sail artisan migrate:fresh --seed
```

`storage:link` は教材画像・プロフィール画像の配信に必要です。`migrate:fresh --seed` でテーブル作成とデモデータ投入が行われます（いつでも再実行してデータを初期状態に戻せます）。

### 7. フロントエンドのビルド

```bash
sail npm install
sail npm run build
```

Blade / CSS / JS を編集しながら開発する場合は、`build` の代わりに `sail npm run dev` を起動したままにしてください（Vite のホットリロードが効きます）。

### 8. 動作確認

http://localhost:8000 にアクセスし、管理者アカウント `admin@certify-lms.test`（パスワード `password`）でログインできればセットアップ完了です（その他のログイン可能なアカウントは次の「ログインアカウント」章の表に記載しています）。

## 開発環境 URL

| 用途 | URL |
|---|---|
| アプリケーション | http://localhost:8000 |
| phpMyAdmin（DB 確認） | http://localhost:8080 |
| Mailpit（メール確認） | http://localhost:8025 |

アプリケーションが送信するメール（招待メールなど）はすべて Mailpit に届きます。実際のメールは送信されません。

## ログインアカウント

`migrate:fresh --seed` 後、以下の固定アカウントが使えます（パスワードはすべて `password`）。

| ロール | メールアドレス | 備考 |
|---|---|---|
| 管理者 | admin@certify-lms.test | 全機能にアクセス可能 |
| コーチ | coach@certify-lms.test | IT 系資格の担当 |
| コーチ | coach2@certify-lms.test | ビジネス系資格の担当 |
| 受講生 | student@certify-lms.test | 受講中の資格・学習履歴・面談などのデモデータ付き |

このほか、ライフサイクル（招待中 / 受講中 / 卒業 / 退会）を網羅したデモユーザーが投入されます。

> **Google カレンダー連携について**: `database/seeders/` 配下に `GoogleCredential` を投入するシードは無いため、`migrate:fresh --seed` 直後はどのコーチも Google カレンダー未連携の状態です。連携済み状態を確認したい場合は、該当コーチ（`coach@certify-lms.test` 等）でログインし、「設定」→「Googleカレンダー連携」画面から「連携する」を押して手動で連携してください。実際のGoogle APIキーの取得方法やOAuth連携の詳細な動作確認手順（FreeBusy反映の確認・イベント作成/削除の確認・連携解除の確認等）は、本README後半の「外部サービス連携のセットアップ・動作確認」章の「1. Google Calendar 連携（面談予約）」にすべて直接記載しています。

> 本サービスは**招待制**です。公開の会員登録画面はありません。新規ユーザーを作るには、管理者でログイン → ユーザー管理から招待 → Mailpit で招待メールの URL を開く → オンボーディング登録、という流れになります。

## キュー（通知・メール送信の非同期処理）

通知・メール送信（招待メール / チャット新着 / Q&A 回答 / 面談リマインダー / お知らせ配信 等）は `ShouldQueue` を実装しており、非同期キュー経由で処理できる構造になっています。`compose.yaml` には `jobs` テーブルを処理する `queue` コンテナ（`php artisan queue:work --tries=3` 常駐）も定義されています。

ただし `.env.example` の既定値は `QUEUE_CONNECTION=sync` のため、**初期状態ではジョブは `jobs` テーブルに積まれず、リクエスト処理と同期的にその場で実行されます**（`queue` コンテナは起動していますが、処理対象のジョブが積まれないため実質的に待機状態です）。

実際に非同期（バックグラウンド）処理を確認したい場合は、以下の手順で `database` キューに切り替えてください。

```env
QUEUE_CONNECTION=database
```

```bash
sail artisan config:clear
```

- 切り替え後は `queue` コンテナが `jobs` テーブルのジョブを自動的に処理します。ログは `sail logs queue` で確認できます。
- 別ターミナルで手動実行したい場合は以下のコマンドでも起動できます。

```bash
sail artisan queue:work --tries=3
```

- 失敗したジョブは `failed_jobs` テーブルに記録されます（`sail artisan queue:failed` で確認、`sail artisan queue:retry all` で再試行）。
- 本番相当の環境では、`queue:work` を systemd や Supervisor 等でプロセス常駐化し、デプロイ時に `queue:restart` でワーカーを再起動する運用を想定してください。

## 外部サービス連携のセットアップ・動作確認

Google Calendar / Gemini AI / Stripe の3つは、`.env.example` に**ダミー値**が入った状態でもアプリ自体は起動します（各機能は「未連携時のフォールバック動作」になります）。実際の外部APIと繋いで動作確認する場合は、以下の手順で各ダミー値を実際の値に置き換えてください。

置き換え対象は `.env` の以下の3ブロックです（`.env.example` にも同じ形でダミー値が入っています）。

```env
GOOGLE_CLIENT_ID="your_google_client_id.apps.googleusercontent.com"
GOOGLE_CLIENT_SECRET=your_google_client_secret_here
GOOGLE_REDIRECT_URI="${APP_URL}/settings/google-calendar/callback"

AI_CHAT_ENABLED=true
GEMINI_API_KEY=your_gemini_api_key_here
GEMINI_MODEL=gemini-3.5-flash-lite
GEMINI_TIMEOUT=60
AI_CHAT_DAILY_LIMIT=100

STRIPE_KEY=pk_test_51PxExamplePublicKeyDummy1234567890abcdef
STRIPE_SECRET=sk_test_51PxExampleSecretKeyDummy1234567890abcdef
STRIPE_WEBHOOK_SECRET=whsec_dummy_webhook_secret_key_1234567890abcdef
```

> **注意**: 本物のAPIキー・シークレットは `.env` にのみ記載し、コミットログやドキュメントに貼り付けないでください（`.env` は `.gitignore` 対象です）。

### 1. Google Calendar 連携（面談予約）

コーチの面談予約の空き枠にGoogleカレンダーの予定（Busy時間）を反映し、予約確定・キャンセル時にGoogleカレンダー側にもイベントを作成・削除する機能です。

**関連ファイル**: `app/Http/Controllers/Settings/GoogleCalendarController.php`（OAuth connect/callback/destroy）/ `app/Services/GoogleCalendarService.php`（FreeBusy取得・イベント作成/削除・トークンリフレッシュ）/ `app/Models/GoogleCredential.php`

#### 1-1. ダミー値 → 実データへの切り替え手順

1. [Google Cloud Console](https://console.cloud.google.com/) にアクセスし、プロジェクトを新規作成（または既存プロジェクトを選択）する。
2. 左メニュー「APIとサービス」→「ライブラリ」から **Google Calendar API** を検索し、有効化する。
3. 「APIとサービス」→「OAuth 同意画面」で、User Type を「外部」にして最低限の情報（アプリ名・サポートメール）を登録する。テスト目的なら「テストユーザー」に自分のGoogleアカウントを追加しておくと、本番公開審査なしで動作確認できる。
4. 「APIとサービス」→「認証情報」→「認証情報を作成」→「OAuth クライアント ID」。アプリケーションの種類は「ウェブアプリケーション」を選択。
5. 「承認済みのリダイレクト URI」に、ローカル環境の実際のURLを登録する。`APP_URL=http://localhost:8000` のままなら以下を登録する。
   ```
   http://localhost:8000/settings/google-calendar/callback
   ```
6. 作成後に表示される「クライアントID」「クライアントシークレット」を控え、`.env` を次のように書き換える。

   ```env
   GOOGLE_CLIENT_ID="123456789012-abcdefghijklmnopqrstuvwxyz123456.apps.googleusercontent.com"
   GOOGLE_CLIENT_SECRET="GOCSPX-xxxxxxxxxxxxxxxxxxxxxxxx"
   GOOGLE_REDIRECT_URI="${APP_URL}/settings/google-calendar/callback"
   ```

   - `GOOGLE_REDIRECT_URI` は項目として存在するが、実装側は `route('settings.google-calendar.callback')` でリダイレクトURIを動的生成しており、この環境変数自体を直接読み込む箇所はない。値を変える必要はなく、Google Cloud Console側の「承認済みのリダイレクトURI」が実際の `APP_URL` と一致していることが重要。

7. 設定を反映する。

   ```bash
   sail artisan config:clear
   ```

#### 1-2. 動作確認手順

1. コーチ（例: `coach@certify-lms.test`）でログインし、「設定」→「Googleカレンダー連携」画面を開く。
2. 「連携する」ボタンを押し、GoogleのOAuth同意画面にリダイレクトされることを確認する。
3. 1-1 手順3で「テストユーザー」に追加したGoogleアカウントでログイン・許可する。
4. コールバック後、連携完了のフラッシュメッセージが表示されることを確認する。
5. **DBチェックポイント**: `google_credentials` テーブルに当該コーチの行が作成され、`access_token` / `refresh_token` / `expires_at` / `connected_at` が入っていることを確認する。
   ```bash
   sail artisan tinker --execute="dd(App\Models\GoogleCredential::where('user_id', App\Models\User::where('email','coach@certify-lms.test')->first()->id)->first())"
   ```
6. Googleカレンダー側に、確認用の予定（Busy時間帯）を1件作成する。
7. 受講生側で面談予約画面（`meetings.availability`）を開き、手順6で作成した時間帯が空き枠から除外されていることを確認する（FreeBusy反映）。
8. 受講生が面談を予約し、連携先のGoogleカレンダーに新しいイベントが作成されることを確認する（`meetings.google_event_id` にイベントIDが保存される）。
9. 面談をキャンセルし、Googleカレンダー側のイベントが削除されることを確認する。
10. **連携解除**: 設定画面で「連携を解除する」を実行し、Google側のアクセス許可も失効（Revoke）され、`google_credentials` の該当行が削除されることを確認する。以後は連携前の動作（FreeBusy反映なし）にフォールバックする。

#### 1-3. ログ・エラー時のチェックポイント

- Google API呼び出し失敗時（トークン失効・ネットワークエラー等）は `null` / `[]` を返し、画面操作自体は失敗させないフォールバック設計になっている。
- 失敗の詳細は `storage/logs/laravel.log` に記録される（`sail logs laravel.test` または `tail -f storage/logs/laravel.log`）。
- `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` が未設定（ダミー値のまま）の場合、連携 UI は表示されるが、OAuth コールバック時にトークン取得が失敗し、「Google トークンの取得に失敗しました。」と案内される。

### 2. Gemini AI チャットボット

受講生が閲覧中の教材（Section）の内容を踏まえて、AIに質問・相談できる機能です。

**関連ファイル**: `config/ai-chat.php` / `app/Services/GeminiApiService.php` / `app/Http/Controllers/AiChatController.php`

#### 2-1. ダミー値 → 実データへの切り替え手順

1. [Google AI Studio](https://aistudio.google.com/) にアクセスし、Googleアカウントでログインする。
2. 左メニュー「Get API key」（または「APIキーを取得」）を選択する。
3. 「Create API key」で新規プロジェクトを選ぶか既存プロジェクトを指定してAPIキーを発行する。
4. 発行されたキー（`AIza...` 形式）を控え、`.env` を次のように書き換える。

   ```env
   AI_CHAT_ENABLED=true
   GEMINI_API_KEY="AIzaSyXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"
   GEMINI_MODEL=gemini-3.5-flash-lite
   GEMINI_TIMEOUT=60
   AI_CHAT_DAILY_LIMIT=100
   ```

   - `GEMINI_API_KEY` のみ実際の値への置き換えが必須。`GEMINI_MODEL` / `GEMINI_TIMEOUT` / `AI_CHAT_DAILY_LIMIT` はダミー値のままでも（未設定でも）`config/ai-chat.php` のデフォルト値で動作する。
   - `AI_CHAT_ENABLED=false` にすると機能自体をOFFにでき、画面には「現在AI相談機能は利用できません」等の案内が表示される。

5. 設定を反映する。

   ```bash
   sail artisan config:clear
   ```

#### 2-2. 動作確認手順

1. 受講生でログインし、「AI相談」画面（`ai-chat.index`）を開く。
2. 新しい会話を開始し、質問を入力して送信する。
3. **正常応答時のチェックポイント**:
   - 画面にAIの返信が表示される。
   - `ai_chat_messages` テーブルに `role=assistant`・成功状態の `status`・`model_name` / `response_time_ms` / `output_tokens` 等のメタデータが保存される（受講生向け画面には表示されないことも合わせて確認する）。
   - 教材（Section）のページから相談を開始した場合、AIの応答が閲覧中の教材内容を踏まえたものになっているか（文脈注入）を確認する。
   - 同じ会話で2回目以降の質問をし、AIが前回のやり取りを踏まえて回答できているか（履歴引き継ぎ）を確認する。
4. **日次上限チェック**: `AI_CHAT_DAILY_LIMIT`（既定100件）を超えて送信しようとした場合、送信がブロックされ案内が表示されることを確認する（確認を簡略化する場合は一時的に `.env` の `AI_CHAT_DAILY_LIMIT=2` 等に下げて試す）。
5. **APIキー未設定・エラー時の確認**: `.env` の `GEMINI_API_KEY` をダミー値や空のままにして `config:clear` 後にアクセスすると、「AI機能が設定されていません」等の案内が表示され、送信操作自体がガードされることを確認する。
6. **API通信エラー時の確認**: Gemini側が一時的にエラーを返すケース（レート制限・5xx等）では自動再試行のうえ、それでも失敗した場合は `ai_chat_messages.status = error` で保存され、画面にエラーメッセージが表示される。手動で再現する場合は `GEMINI_API_KEY` に不正な値を入れて送信し、エラー表示とDB保存内容（`error_detail`）を確認する。

#### 2-3. ログ・DBチェックポイント

- `storage/logs/laravel.log` にGemini APIからのエラーレスポンスが記録される。

### 3. Stripe 連携（追加面談購入）

受講生が追加の面談パックをStripe Checkoutで購入し、Webhook経由で決済結果を反映する機能です。

**関連ファイル**: `app/Http/Controllers/MeetingQuotaCheckoutController.php`（Checkout開始・success画面）/ `app/Http/Controllers/StripeWebhookController.php`（Webhook受信、`/webhooks/stripe`、CSRF除外済み）/ `app/Services/StripePaymentService.php` / `app/Models/Payment.php`

#### 3-1. ダミー値 → 実データへの切り替え手順

1. [Stripeダッシュボード](https://dashboard.stripe.com/) にログイン（アカウントが無ければ無料で作成）し、右上の「テストモード」を有効にする。
2. 「開発者」→「APIキー」画面で以下を控える。
   - **公開可能キー**（`pk_test_...`）→ `STRIPE_KEY`
   - **シークレットキー**（`sk_test_...`）→ `STRIPE_SECRET`
3. [Stripe CLI](https://stripe.com/docs/stripe-cli) をインストールし、ログインする。

   ```bash
   stripe login
   ```

4. ローカルのWebhookエンドポイントへ転送を開始する（Laravel Sailがポート8000で動いている場合）。

   ```bash
   stripe listen --forward-to localhost:8000/webhooks/stripe
   ```

   実行すると、ターミナルに `whsec_...` で始まる一時的なWebhookシークレットが表示される。これを `STRIPE_WEBHOOK_SECRET` に使う。

5. 控えた値で `.env` を書き換える。

   ```env
   STRIPE_KEY="pk_test_51XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"
   STRIPE_SECRET="sk_test_51XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"
   STRIPE_WEBHOOK_SECRET="whsec_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"
   ```

6. 設定を反映する。

   ```bash
   sail artisan config:clear
   ```

   （`stripe listen` を起動したターミナルは動作確認中そのまま起動し続けておく。）

#### 3-2. 動作確認手順

1. 受講生でログインし、追加面談パックの購入画面を開き、面談パックを選択してCheckoutを開始する。
2. Stripeのテスト用Checkoutページにリダイレクトされる。テスト用カード番号 `4242 4242 4242 4242`（任意の将来の有効期限・任意のCVC）で決済を完了する。
3. 購入完了画面（`meeting-quota.success`）にリダイレクトされることを確認する。
4. `stripe listen` のターミナルに `checkout.session.completed` イベントが転送され、アプリ側（`/webhooks/stripe`）が200を返していることを確認する。
5. **DBチェックポイント**: `payments` テーブルに該当レコードが作成され `status = succeeded` になっていること、`meeting_quota_transactions` に `type = purchased` の行が追加され受講生の面談残数に反映されていることを確認する。
   ```bash
   sail artisan tinker --execute="dd(App\Models\Payment::latest()->first())"
   ```
6. **Webhookの手動イベント発火**（決済フローを通さずイベント単体を試す場合）:
   ```bash
   stripe trigger checkout.session.completed
   ```
7. **決済未完了（セッション期限切れ）の確認**: Checkout画面を開いたまま決済せず放置する、または以下のコマンドで単体発火し、`payments` が `pending` のまま残らず `status = failed` に遷移することを確認する。
   ```bash
   stripe trigger checkout.session.expired
   ```
8. **署名検証・冪等性の確認**:
   - 署名ヘッダを欠いた状態、または不正な署名でPOSTした場合、`StripeWebhookController` が400を返すこと。
   - 同一イベントが重複して届いても、`Payment` の状態チェックにより二重に残数加算されないこと（`sail artisan test --filter=StripeWebhook` で自動テスト済み）。

## テスト

```bash
sail artisan test                  # 全テスト実行
sail artisan test --filter=Xxx    # クラス名・メソッド名で絞り込み
```

外部連携（Google / Stripe）のHTTP通信をモックするテストには `@group external-api` が付与されています。

## コード整形

Laravel Pint を使用しています。コミット前に実行してください。

```bash
sail bin pint --dirty    # 変更ファイルのみ整形
sail bin pint --test     # 整形漏れの確認（CI 相当のチェック）
```

## 使用技術

- PHP 8.5 / Laravel 10
- MySQL 8.4
- Laravel Fortify（認証）/ Laravel Sanctum（API 認証）
- Blade + Tailwind CSS + Vite（JavaScript は素の JS、フレームワーク不使用）
- PHPUnit / Laravel Pint
- league/commonmark（教材本文の Markdown レンダリング）
- Pusher（チャットのリアルタイム配信）
- Google Calendar API（コーチの面談予約連携）
- Google Gemini API（AI 相談チャットボット）
- Stripe（追加面談パックの購入）
- Docker（Laravel Sail）

## ER図（データベース構成）

`database/migrations/` 配下は、テーブル作成系 45 ファイル（1ファイルで5テーブルを作成する `mock_exam_tables` を含め 49 テーブル）+ カラム追加・制約変更系 8 ファイル（`users` 3件 / `meeting_quota_transactions` 1件 / `meetings` 4件）の全53ファイルで構成されています（Laravel標準の `users` / `password_reset_tokens` / `personal_access_tokens` / `failed_jobs` / `jobs` を含む）。ドメインごとに8クラスタに分けて記載します。

### 1. ユーザー・認証・契約

```mermaid
erDiagram
    users ||--o{ invitations : "招待する"
    users ||--o{ user_status_logs : "状態変更履歴"
    plans ||--o{ users : "割り当てられる"
    plans ||--o{ user_plan_logs : "履歴を持つ"
    users ||--o{ user_plan_logs : "履歴を持つ"
    users ||--o{ meeting_quota_transactions : "面談回数取引"
    meeting_packs ||--o{ payments : "購入される"
    users ||--o{ payments : "決済する"
    users ||--|| google_credentials : "連携する"

    users {
        ulid id PK
        string name "nullable"
        string email UK
        string password "nullable"
        string role "student/coach/admin"
        string status "default: invited"
        text bio "nullable"
        string avatar_url "nullable"
        boolean profile_setup_completed "default false"
        timestamp email_verified_at "nullable"
        ulid plan_id FK "nullable, -> plans, RESTRICT"
        timestamp plan_started_at "nullable"
        timestamp plan_expires_at "nullable"
        smallint max_meetings "default 0"
        string meeting_url "nullable, 500桁"
        ulid default_enrollment_id FK "nullable, -> enrollments, SET NULL"
        timestamp last_login_at "nullable"
        string remember_token
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at "softDeletes"
    }

    password_reset_tokens {
        string email PK
        string token
        timestamp created_at "nullable"
    }

    personal_access_tokens {
        bigint id PK
        string tokenable_type "morphs"
        ulid tokenable_id
        string name
        string token UK "64桁"
        text abilities "nullable"
        timestamp last_used_at "nullable"
        timestamp expires_at "nullable"
        timestamp created_at
        timestamp updated_at
    }

    user_status_logs {
        ulid id PK
        ulid user_id FK "-> users, RESTRICT"
        string from_status
        string to_status
        ulid changed_by_user_id FK "nullable -> users, RESTRICT"
        text changed_reason "nullable"
        timestamp changed_at
        timestamp created_at
        timestamp updated_at
    }

    invitations {
        ulid id PK
        ulid user_id FK "-> users, RESTRICT"
        string email
        string role
        ulid invited_by_user_id FK "-> users, RESTRICT"
        timestamp expires_at
        timestamp accepted_at "nullable"
        timestamp revoked_at "nullable"
        string status "default: pending"
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at "softDeletes"
    }

    plans {
        ulid id PK
        string name "100桁"
        text description "nullable"
        smallint duration_days
        smallint default_meeting_quota
        string status "20桁, default: draft"
        int sort_order "default 0"
        ulid created_by_user_id FK "-> users, RESTRICT"
        ulid updated_by_user_id FK "-> users, RESTRICT"
        timestamp created_at
        timestamp updated_at
    }

    user_plan_logs {
        ulid id PK
        ulid user_id FK "-> users, RESTRICT"
        ulid plan_id FK "-> plans, RESTRICT"
        string event_type "20桁"
        timestamp plan_started_at
        timestamp plan_expires_at
        smallint meeting_quota_initial
        ulid changed_by_user_id FK "nullable -> users, SET NULL"
        string changed_reason "nullable, 200桁"
        timestamp occurred_at
        timestamp created_at
        timestamp updated_at
    }

    meeting_packs {
        ulid id PK
        string name "100桁"
        text description "nullable"
        smallint meeting_count
        int price
        string stripe_price_id "nullable, 255桁"
        string status "20桁, default: draft"
        int sort_order "default 0"
        ulid created_by_user_id FK "-> users, RESTRICT"
        ulid updated_by_user_id FK "-> users, RESTRICT"
        timestamp created_at
        timestamp updated_at
    }

    meeting_quota_transactions {
        ulid id PK
        ulid user_id FK "-> users, RESTRICT"
        string type "30桁"
        int amount
        ulid related_meeting_id FK "nullable -> meetings(他クラスタ), RESTRICT(後付けFK)"
        ulid related_payment_id "nullable, FK制約なし"
        ulid granted_by_user_id FK "nullable -> users, SET NULL"
        string note "nullable, 500桁"
        timestamp occurred_at
        timestamp created_at
        timestamp updated_at
    }

    google_credentials {
        bigint id PK
        ulid user_id FK "UNIQUE -> users, CASCADE"
        text access_token
        text refresh_token "nullable"
        timestamp expires_at "nullable"
        string calendar_id "default: primary"
        timestamp connected_at "nullable"
        timestamp created_at
        timestamp updated_at
    }

    payments {
        bigint id PK
        ulid user_id FK "-> users, CASCADE"
        ulid meeting_pack_id FK "-> meeting_packs, CASCADE"
        string stripe_session_id "nullable, indexed"
        string stripe_payment_intent_id "nullable, indexed"
        int amount
        int quantity
        string status "default: pending, indexed"
        timestamp paid_at "nullable"
        timestamp created_at
        timestamp updated_at
    }
```

補足: `meeting_quota_transactions.related_meeting_id` は `meetings` テーブルが存在しない環境でのmigrate失敗を避けるため、作成時はFK制約なしで `ulid` カラムのみ追加し、`2026_05_30_000000_add_related_meeting_fk_to_meeting_quota_transactions.php` で後からFK制約を付与しています（`Schema::hasTable('meetings')` チェック付き）。`related_payment_id` は現在もFK制約なしです。

### 2. 資格・受講

```mermaid
erDiagram
    certification_categories ||--o{ certifications : "分類する"
    certifications ||--o{ certification_coach_assignments : "コーチを割当"
    certifications ||--o{ enrollments : "受講される"
    users ||--o{ enrollments : "受講する"
    enrollments ||--o{ enrollment_status_logs : "状態変更履歴"
    enrollments ||--|| certificates : "修了証発行"
    enrollments ||--o{ enrollment_goals : "学習目標"
    enrollments ||--o{ enrollment_notes : "コーチメモ"
    users ||--o{ certification_coach_assignments : "コーチとして割当"

    certification_categories {
        ulid id PK
        string slug UK "60桁"
        string name "50桁"
        uint sort_order "default 0"
        timestamp created_at
        timestamp updated_at
    }

    certifications {
        ulid id PK
        string name "100桁"
        ulid category_id FK "-> certification_categories, RESTRICT"
        string difficulty "20桁"
        text description "nullable"
        string status "20桁, default: draft"
        ulid created_by_user_id FK "-> users, RESTRICT"
        ulid updated_by_user_id FK "-> users, RESTRICT"
        timestamp published_at "nullable"
        timestamp archived_at "nullable"
        timestamp created_at
        timestamp updated_at
    }

    certification_coach_assignments {
        ulid id PK
        ulid certification_id FK "-> certifications, CASCADE"
        ulid user_id FK "-> users(コーチ), RESTRICT"
        ulid assigned_by_user_id FK "-> users, RESTRICT"
        timestamp assigned_at
        timestamp unassigned_at "nullable"
        timestamp created_at
        timestamp updated_at
    }

    enrollments {
        ulid id PK
        ulid user_id FK "-> users, RESTRICT"
        ulid certification_id FK "-> certifications, RESTRICT"
        date exam_date "nullable"
        string status "20桁, default: learning"
        string current_term "30桁, default: basic_learning"
        timestamp passed_at "nullable"
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at "softDeletes"
    }

    enrollment_status_logs {
        ulid id PK
        ulid enrollment_id FK "-> enrollments, CASCADE"
        string from_status "nullable, 20桁"
        string to_status "20桁"
        ulid changed_by_user_id FK "nullable -> users, SET NULL"
        timestamp changed_at
        string changed_reason "nullable, 200桁"
        timestamp created_at
        timestamp updated_at
    }

    certificates {
        ulid id PK
        ulid user_id FK "-> users, RESTRICT"
        ulid enrollment_id FK "UNIQUE -> enrollments, RESTRICT"
        ulid certification_id FK "-> certifications, RESTRICT"
        string pdf_path "255桁"
        timestamp issued_at
        timestamp created_at
        timestamp updated_at
    }

    enrollment_goals {
        ulid id PK
        ulid enrollment_id FK "-> enrollments, CASCADE"
        string title "100桁"
        text description "nullable"
        date target_date "nullable"
        timestamp achieved_at "nullable"
        timestamp created_at
        timestamp updated_at
    }

    enrollment_notes {
        ulid id PK
        ulid enrollment_id FK "-> enrollments, CASCADE"
        ulid author_id FK "-> users, RESTRICT"
        text body
        timestamp created_at
        timestamp updated_at
    }
```

制約注記: `enrollments` は `(user_id, certification_id)` にUNIQUE制約あり。`certification_coach_assignments` は `(certification_id, user_id)` にUNIQUE（制約名 `cert_coach_assign_uq`）。

### 3. 教材（Part / Chapter / Section 階層）

```mermaid
erDiagram
    certifications ||--o{ parts : "教材を持つ"
    parts ||--o{ chapters : "含む"
    chapters ||--o{ sections : "含む"
    sections ||--o{ section_images : "画像を持つ"
    sections ||--o{ section_questions : "演習問題"
    certifications ||--o{ question_categories : "出題分野マスタ"
    question_categories ||--o{ section_questions : "分類する"
    section_questions ||--o{ section_question_options : "選択肢"

    parts {
        ulid id PK
        ulid certification_id FK "-> certifications(他クラスタ), RESTRICT"
        string title "200桁"
        text description "nullable"
        uint order "default 1"
        string status "20桁, default: draft"
        timestamp published_at "nullable"
        timestamp created_at
        timestamp updated_at
    }

    chapters {
        ulid id PK
        ulid part_id FK "-> parts, RESTRICT"
        string title "200桁"
        text description "nullable"
        uint order "default 1"
        string status "20桁, default: draft"
        timestamp published_at "nullable"
        timestamp created_at
        timestamp updated_at
    }

    sections {
        ulid id PK
        ulid chapter_id FK "-> chapters, RESTRICT"
        string title "200桁, indexed"
        text description "nullable"
        longtext body "Markdown本文"
        uint order "default 1"
        string status "20桁, default: draft"
        timestamp published_at "nullable"
        timestamp created_at
        timestamp updated_at
    }

    section_images {
        ulid id PK
        ulid section_id FK "-> sections, RESTRICT"
        string path UK "255桁"
        string original_filename "255桁"
        string mime_type "100桁"
        uint size_bytes
        timestamp created_at
        timestamp updated_at
    }

    question_categories {
        ulid id PK
        ulid certification_id FK "-> certifications(他クラスタ), RESTRICT"
        string name "50桁"
        string slug "60桁"
        uint sort_order "default 0"
        text description "nullable"
        timestamp created_at
        timestamp updated_at
    }

    section_questions {
        ulid id PK
        ulid section_id FK "-> sections, CASCADE"
        ulid category_id FK "-> question_categories, RESTRICT"
        text body
        text explanation "nullable"
        usmallint order "default 0"
        string status "20桁, default: draft"
        timestamp published_at "nullable"
        timestamp created_at
        timestamp updated_at
    }

    section_question_options {
        ulid id PK
        ulid section_question_id FK "-> section_questions, CASCADE"
        text body
        boolean is_correct "default false"
        usmallint order "default 0"
        timestamp created_at
        timestamp updated_at
    }
```

制約注記: `question_categories` は `(certification_id, slug)` にUNIQUE（制約名 `question_categories_cert_slug_uq`）。

### 4. 演習解答・模試

```mermaid
erDiagram
    users ||--o{ section_question_answers : "解答する"
    section_questions ||--o{ section_question_answers : "解答される"
    section_question_options ||--o{ section_question_answers : "選択される"
    users ||--o{ section_question_attempts : "累計サマリ"
    section_questions ||--o{ section_question_attempts : "集計対象"
    certifications ||--o{ mock_exams : "模試を持つ"
    mock_exams ||--o{ mock_exam_questions : "問題を持つ"
    question_categories ||--o{ mock_exam_questions : "分類する"
    mock_exam_questions ||--o{ mock_exam_question_options : "選択肢"
    mock_exams ||--o{ mock_exam_sessions : "受験される"
    enrollments ||--o{ mock_exam_sessions : "受験記録"
    users ||--o{ mock_exam_sessions : "受験する"
    mock_exam_sessions ||--o{ mock_exam_answers : "解答を持つ"
    mock_exam_questions ||--o{ mock_exam_answers : "解答される"
    mock_exam_question_options ||--o{ mock_exam_answers : "選択される"

    section_question_answers {
        ulid id PK
        ulid user_id FK "-> users, RESTRICT"
        ulid section_question_id FK "-> section_questions, RESTRICT"
        ulid selected_option_id FK "nullable -> section_question_options, SET NULL"
        string selected_option_body "2000桁 (スナップショット)"
        boolean is_correct
        string source "20桁"
        timestamp answered_at
        timestamp created_at
        timestamp updated_at
    }

    section_question_attempts {
        ulid id PK
        ulid user_id FK "-> users, RESTRICT"
        ulid section_question_id FK "-> section_questions, RESTRICT"
        uint attempt_count "default 0"
        uint correct_count "default 0"
        boolean last_is_correct "default false"
        timestamp last_answered_at
        timestamp created_at
        timestamp updated_at
    }

    mock_exams {
        ulid id PK
        ulid certification_id FK "-> certifications(他クラスタ), RESTRICT"
        string title "100桁"
        text description "nullable"
        usmallint order "default 0"
        utinyint passing_score
        boolean is_published "default false"
        timestamp published_at "nullable"
        ulid created_by_user_id FK "-> users, RESTRICT"
        ulid updated_by_user_id FK "-> users, RESTRICT"
        timestamp created_at
        timestamp updated_at
    }

    mock_exam_questions {
        ulid id PK
        ulid mock_exam_id FK "-> mock_exams, CASCADE"
        ulid category_id FK "-> question_categories(他クラスタ), RESTRICT"
        text body
        text explanation "nullable"
        usmallint order "default 0"
        timestamp created_at
        timestamp updated_at
    }

    mock_exam_question_options {
        ulid id PK
        ulid mock_exam_question_id FK "-> mock_exam_questions, CASCADE"
        text body
        boolean is_correct
        usmallint order
        timestamp created_at
        timestamp updated_at
    }

    mock_exam_sessions {
        ulid id PK
        ulid mock_exam_id FK "-> mock_exams, RESTRICT"
        ulid enrollment_id FK "-> enrollments(他クラスタ), RESTRICT"
        ulid user_id FK "-> users, RESTRICT"
        string status "20桁, default: not_started"
        json generated_question_ids
        usmallint total_questions
        utinyint passing_score_snapshot
        timestamp started_at "nullable"
        timestamp submitted_at "nullable"
        timestamp graded_at "nullable"
        timestamp canceled_at "nullable"
        usmallint total_correct "nullable"
        decimal score_percentage "5,2 nullable"
        boolean pass "nullable"
        timestamp created_at
        timestamp updated_at
    }

    mock_exam_answers {
        ulid id PK
        ulid mock_exam_session_id FK "-> mock_exam_sessions, CASCADE"
        ulid mock_exam_question_id FK "-> mock_exam_questions, RESTRICT"
        ulid selected_option_id FK "nullable -> mock_exam_question_options, SET NULL"
        string selected_option_body "2000桁"
        boolean is_correct "default false"
        timestamp answered_at
        timestamp created_at
        timestamp updated_at
    }
```

制約注記: `section_question_attempts` は `(user_id, section_question_id)` にUNIQUE。`mock_exam_answers` は `(mock_exam_session_id, mock_exam_question_id)` にUNIQUE（制約名 `mock_exam_answers_session_question_unique`）。`mock_exams` / `mock_exam_questions` / `mock_exam_question_options` / `mock_exam_sessions` / `mock_exam_answers` の5テーブルは `2026_05_17_000023_create_mock_exam_tables.php` 1ファイルでまとめて作成されています。

### 5. 学習進捗

```mermaid
erDiagram
    enrollments ||--o{ section_progresses : "読了マーク"
    sections ||--o{ section_progresses : "読了される"
    users ||--o{ learning_sessions : "学習する"
    enrollments ||--o{ learning_sessions : "学習記録"
    sections ||--o{ learning_sessions : "閲覧される"
    enrollments ||--|| learning_hour_targets : "学習時間目標"

    section_progresses {
        ulid id PK
        ulid enrollment_id FK "-> enrollments(他クラスタ), RESTRICT"
        ulid section_id FK "-> sections(他クラスタ), RESTRICT"
        timestamp completed_at
        timestamp created_at
        timestamp updated_at
    }

    learning_sessions {
        ulid id PK
        ulid user_id FK "-> users(他クラスタ), RESTRICT"
        ulid enrollment_id FK "-> enrollments(他クラスタ), RESTRICT"
        ulid section_id FK "-> sections(他クラスタ), RESTRICT"
        timestamp started_at
        timestamp ended_at "nullable"
        uint duration_seconds "nullable"
        boolean auto_closed "default false"
        timestamp created_at
        timestamp updated_at
    }

    learning_hour_targets {
        ulid id PK
        ulid enrollment_id FK "UNIQUE -> enrollments(他クラスタ), RESTRICT"
        usmallint target_total_hours
        timestamp created_at
        timestamp updated_at
    }
```

制約注記: `section_progresses` は `(enrollment_id, section_id)` にUNIQUE。`learning_hour_targets.enrollment_id` はUNIQUE（1対1）。

### 6. 面談

```mermaid
erDiagram
    enrollments ||--o{ meetings : "面談予約"
    users ||--o{ meetings : "コーチとして実施"
    users ||--o{ meetings : "受講生として参加"
    meeting_quota_transactions ||--o{ meetings : "消費元取引"
    meetings ||--|| meeting_memos : "メモを持つ"
    users ||--o{ coach_availabilities : "対応可能時間帯"

    meetings {
        ulid id PK
        ulid enrollment_id FK "-> enrollments(他クラスタ), RESTRICT"
        ulid coach_id FK "-> users(他クラスタ), RESTRICT"
        ulid student_id FK "-> users(他クラスタ), RESTRICT"
        datetime scheduled_at
        datetime reserved_scheduled_at "生成列(stored): status=reserved時のscheduled_at、それ以外NULL"
        string status "20桁"
        text topic
        ulid canceled_by_user_id FK "nullable -> users, SET NULL"
        datetime canceled_at "nullable"
        string meeting_url_snapshot "nullable, 500桁"
        string google_event_id "nullable"
        timestamp reminded_eve_at "nullable"
        timestamp reminded_one_hour_at "nullable"
        datetime completed_at "nullable"
        ulid meeting_quota_transaction_id FK "nullable -> meeting_quota_transactions(他クラスタ), SET NULL"
        timestamp created_at
        timestamp updated_at
    }

    meeting_memos {
        ulid id PK
        ulid meeting_id FK "UNIQUE -> meetings, CASCADE"
        text body
        timestamp created_at
        timestamp updated_at
    }

    coach_availabilities {
        ulid id PK
        ulid coach_id FK "-> users(他クラスタ), CASCADE"
        utinyint day_of_week "0=日曜〜6=土曜"
        time start_time
        time end_time
        boolean is_active "default true"
        timestamp created_at
        timestamp updated_at
    }
```

**ユニーク制約の変遷（重要）**:
1. `2026_11_16_082121_add_unique_to_meetings_table.php`: `(coach_id, scheduled_at)` にUNIQUE追加。
2. `2026_11_23_171153_update_meetings_unique_constraint.php`: 生成列 `reserved_scheduled_at`（`status='reserved'` の時だけ `scheduled_at` を保持、それ以外はNULL）を追加し、`(coach_id, scheduled_at)` のUNIQUEを削除、代わりに `(coach_id, reserved_scheduled_at)` にUNIQUE（制約名 `meetings_coach_id_reserved_scheduled_at_unique`）を設定。
   - **最終状態**: `(coach_id, scheduled_at)` の直接UNIQUEは存在しない。`reserved` 状態の面談のみ、同コーチ×同時刻の重複がDBレベルで禁止される（NULL同士はUNIQUE制約に抵触しないため、キャンセル済み・完了済みの面談は同時刻に複数存在できる）。

### 7. コミュニケーション（チャット / Q&A / お知らせ / 通知）

```mermaid
erDiagram
    enrollments ||--|| chat_rooms : "チャットルームを持つ"
    chat_rooms ||--o{ chat_members : "参加者"
    users ||--o{ chat_members : "参加する"
    chat_rooms ||--o{ chat_messages : "メッセージ"
    users ||--o{ chat_messages : "送信する"
    users ||--o{ qa_threads : "質問する"
    certifications ||--o{ qa_threads : "資格に紐づく"
    qa_threads ||--o{ qa_replies : "回答を持つ"
    users ||--o{ qa_replies : "回答する"
    certifications ||--o{ announcements : "対象資格"
    users ||--o{ announcements : "対象ユーザー/作成者"
    users ||--o{ notifications : "通知される(morph)"

    chat_rooms {
        ulid id PK
        ulid enrollment_id FK "UNIQUE -> enrollments(他クラスタ), RESTRICT"
        timestamp last_message_at "nullable, denormalized"
        timestamp created_at
        timestamp updated_at
    }

    chat_members {
        ulid id PK
        ulid chat_room_id FK "-> chat_rooms, CASCADE"
        ulid user_id FK "-> users(他クラスタ), RESTRICT"
        timestamp last_read_at "nullable"
        timestamp joined_at
        timestamp created_at
        timestamp updated_at
    }

    chat_messages {
        ulid id PK
        ulid chat_room_id FK "-> chat_rooms, CASCADE"
        ulid sender_user_id FK "-> users(他クラスタ), RESTRICT"
        text body
        timestamp created_at
        timestamp updated_at
    }

    qa_threads {
        bigint id PK
        ulid user_id FK "-> users(他クラスタ), CASCADE"
        ulid certification_id FK "-> certifications(他クラスタ), CASCADE"
        string title "200桁"
        text body
        string status "20桁, default: unresolved"
        timestamp resolved_at "nullable"
        timestamp created_at
        timestamp updated_at
    }

    qa_replies {
        bigint id PK
        bigint qa_thread_id FK "-> qa_threads, CASCADE"
        ulid user_id FK "-> users(他クラスタ), CASCADE"
        text body
        timestamp created_at
        timestamp updated_at
    }

    announcements {
        ulid id PK
        string title "200桁"
        text body
        string target_type "30桁"
        ulid target_certification_id FK "nullable -> certifications(他クラスタ), SET NULL"
        ulid target_user_id FK "nullable -> users(他クラスタ), SET NULL"
        uint dispatched_count "default 0"
        timestamp dispatched_at "nullable"
        ulid created_by_user_id FK "-> users(他クラスタ), RESTRICT"
        timestamp created_at
        timestamp updated_at
    }

    notifications {
        uuid id PK
        string type
        string notifiable_type "ulidMorphs"
        ulid notifiable_id
        text data
        timestamp read_at "nullable"
        timestamp created_at
        timestamp updated_at
    }
```

制約注記: `chat_rooms.enrollment_id` はUNIQUE（1対1）。`chat_members` は `(chat_room_id, user_id)` にUNIQUE。`qa_threads` / `qa_replies` の主キーは他の業務テーブルと異なり `bigint`（`$table->id()`）であり、ULIDではありません。

### 8. AI・システム（キュー / バッチ）

```mermaid
erDiagram
    users ||--o{ ai_chat_conversations : "会話する"
    enrollments ||--o{ ai_chat_conversations : "受講中資格の文脈"
    sections ||--o{ ai_chat_conversations : "閲覧中Sectionの文脈"
    ai_chat_conversations ||--o{ ai_chat_messages : "メッセージ"

    ai_chat_conversations {
        ulid id PK
        ulid user_id FK "-> users(他クラスタ), CASCADE"
        ulid enrollment_id FK "nullable -> enrollments(他クラスタ), SET NULL"
        ulid section_id FK "nullable -> sections(他クラスタ), SET NULL"
        string title "100桁"
        timestamp last_message_at "nullable"
        boolean auto_title_enabled "default true"
        timestamp created_at
        timestamp updated_at
    }

    ai_chat_messages {
        ulid id PK
        ulid ai_chat_conversation_id FK "-> ai_chat_conversations, CASCADE"
        string role "20桁: user/assistant"
        text content
        string status "20桁, default: completed (pending/completed/error)"
        text error_detail "nullable"
        string model_name "nullable, 100桁"
        uint input_tokens "nullable"
        uint output_tokens "nullable"
        uint response_time_ms "nullable"
        timestamp created_at
        timestamp updated_at
    }

    jobs {
        bigint id PK
        string queue "indexed"
        longtext payload
        utinyint attempts
        uint reserved_at "nullable"
        uint available_at
        uint created_at
    }

    failed_jobs {
        bigint id PK
        string uuid UK
        text connection
        text queue
        longtext payload
        longtext exception
        timestamp failed_at
    }
```

補足: `jobs` / `failed_jobs` はリレーションを持たない独立したLaravel標準インフラテーブル（キューワーカー用）です。`QUEUE_CONNECTION=sync`（既定値）の間は `jobs` テーブルに行は積まれません。`database` に切り替えた場合のみジョブが積まれ、`queue` コンテナが処理します。

## 環境変数

`.env.example` をコピーするだけで、すべての機能がローカルで動作します（メールは Mailpit に配信されます。Google / Gemini / Stripe はダミー値のためフォールバック動作になります）。

- `PUSHER_*` — チャットのリアルタイム配信に使用します。有効にする場合は Pusher のキーを取得して設定し、`BROADCAST_DRIVER=pusher` に変更してください。未設定（既定の `BROADCAST_DRIVER=log`）でもメッセージの送受信自体は動作し、相手画面へのリアルタイム反映のみ行われません。
- `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `AI_CHAT_ENABLED` / `GEMINI_API_KEY` / `GEMINI_MODEL` / `GEMINI_TIMEOUT` / `AI_CHAT_DAILY_LIMIT` / `STRIPE_KEY` / `STRIPE_SECRET` / `STRIPE_WEBHOOK_SECRET` — Google Calendar / Gemini AI / Stripe の3連携に使用する変数です。それぞれのAPIキー取得手順・`.env`書き換え手順・画面操作による動作確認手順・DBチェックポイントは、本README内の「外部サービス連携のセットアップ・動作確認」章に直接記載しています（該当章は「環境構築手順」のあと、「キュー（通知・メール送信の非同期処理）」章の直後に続きます）。
- 管理者ダッシュボードの集計（全体 KPI・資格別修了率）は `config/dashboard.php` の `cache_ttl`（既定値 `3600` 秒 = 1 時間、現時点では環境変数化されておらずコード直編集が必要）でキャッシュされます。受講状態の遷移（合格 / 不合格 / 退会 等）が起きた場合は、このTTLを待たずに該当キャッシュが即時無効化されます。

新しい環境変数やセットアップ手順を追加した場合は、`.env.example` と本 README に追記し、チームの誰でも環境を再現できる状態を保ってください。
