# サムネイル付きリンク生成サービス

このプロジェクトは、ユーザーが指定したURLへのリンクを生成し、そのリンクにサムネイル画像などのメタ情報を付与するウェブサービスです。  
本サービスを使用することで、TwitterやSNSでシェアした際に、見栄えの良いサムネイル付きのリンクを生成できます。

---

## 特徴

1. **サムネイル画像の選択方法を3種類サポート**  
   - **画像URLを入力**：外部からの画像URLを指定可能  
   - **画像ファイルをアップロード**：ローカルPCやスマートフォンから直接アップロード  
   - **テンプレートから選択**：あらかじめ用意した画像テンプレートから選択  

2. **レスポンシブデザイン対応**  
   - スマホの縦画面でもPCでも、綺麗に表示されるUIを実現

3. **CSRF対策・セキュリティ強化**  
   - フォーム送信時にCSRFトークンを使用して、不正なリクエストを防止

4. **リンク生成形式の向上**  
   - 生成されたリンクを「`ドメイン/ユニークID/`」というフォルダ形式にし、`.html`拡張子を隠蔽  
   - よりシンプルで美しいURLを実現

5. **メタタグ対応**  
   - Twitterカード等、SNSでのプレビューが自動で表示されるようメタタグを自動生成

6. **画像プレビュー機能**  
   - クライアント側（ブラウザ）でCanvas APIを用いて画像を2:1にトリミングし、プレビュー表示

---

## 動作環境

- **PHP** 7.4 以上
  - **GDライブラリ**：画像処理のため必須
  - **cURL**：画像URLからの取得に使用
- **Webサーバー** (Apache / Nginx / IIS など)
- **SSL証明書** (推奨)  
  - HTTPSで通信することでセキュリティを確保

---

## ディレクトリ構成

