# Query_String
* HTMLのGET/POST methodで送られる文字列に関する情報です。
* 指定方法：GETはURLのパラメタとしてQUERY_STRINGで指定、POSTはPOST_MESSAGEとして指定を表します。
* GETとPOSTの両方に指定されている場合はGETを優先します。
* 複数のパラメータを指定する場合は、パラメータは カンマ " " \r \t \n 等で区切ります。


***

## ■■■表示系項目■■■
* 指定方法：GET(推奨)・POST


#### p：ページ番号
* タイプ：数値
* 表示内容が複数ページにわたる場合に、何ページ目を表示するかを表します。
* 指定しない場合は1ページ目を表します。
* 最終ページより大きいページ数を指定した場合には、表示項目が0個になります (3ページしかない＝4ページ目に表示する項目は無い、という意味です)


***

## ■■■検索・フィルタ条件系項目■■■
* 指定方法：GET(推奨)・POST
* これらの指定では、複数の値をカンマ・半角スペースで区切って指定できます。たとえば数値型のものに1,2,3等を指定可能です。
* 複数指定のばあいOR検索になります。


#### id：DB内ID (商品コードではありません)
* タイプ：数値、またはその組

#### itemCode：商品コード
* タイプ：文字列、またはその組。空文字列可
* ItemCodeは、ASIN, ISBN13, ISBN10 のいずれかで指定 (数値以外に英字を含む場合あり)

#### place：保存場所
* タイプ：数値、またはその組。
* 検索等で任意の値を表したいときは0を指定する
* 文字列ではないことに注意

#### state：未読既読状態
* タイプ：数値、またはその組
* 検索等で任意の値を表したいときは0を指定する
* 文字列ではないことに注意

#### tag：タグ
* タイプ：数値、またはその組
* 検索等で任意の値を表したいときは0を指定する
* 文字列ではないことに注意

#### title：タイトル
* タイプ：文字列、またはその組。空文字列可
* 正規表現ではありません

#### author：著者
* タイプ：文字列、またはその組。空文字列可
* 正規表現ではありません

### publisher：出版社
* タイプ：文字列、またはその組。空文字列可
* 正規表現ではありません

#### memo：メモ
* タイプ：文字列、またはその組。空文字列可
* 正規表現ではありません


***

## ■■■動作系■■■
* 指定方法：POST。GETで指定しても無視されます。


#### action: 動作
* タイプ：特定の文字列。大文字小文字区別あり
* addItem, addPlace, addState, addTag, AddTagRef：追加
* modifyItem, modifyPlace, modifyState, modifyTag：情報変更
* delItem, delPlace, delState, delTag, delTagRef：削除


***

## ■■■動作系での項目指定■■■
* 指定方法：POST。GETで指定しても無視されます。


#### targetItem：変更・削除対象のID
* タイプ：数値
* 指定するのはDB内のIDであって、商品コードではありません
* 以下の場合に指定：action=modifyMemo, delItem

#### targetPlace：変更・削除対象のID
* タイプ：数値
* 以下の場合に指定：action=modifyPlace, delPlace

#### targetState：変更・削除対象のID
* タイプ：数値
* 以下の場合に指定：action=modifyState, delState

#### targetTag：変更・削除対象のID
* タイプ：数値
* 以下の場合に指定：action=modifyTag, delTag



#### newItemCode：追加する項目の商品コード
* タイプ：文字列
* 以下の場合に指定：action=addItem
* ItemCodeは、ASIN, ISBN13, ISBN10 のいずれかで指定 (数値以外に英字を含む場合あり)
* 省略可能。省略した場合は newTitle の指定が必要になる。


#### newPlace：追加・変更する項目の場所
* タイプ：文字列
* 以下の場合に指定：action=addItem, addPlace, modifyPlace


#### newState：追加・変更する項目の未読既読状態
* タイプ：文字列
* 以下の場合に指定：action=addItem, addState, modifyState


#### newTag：追加・変更するタグ
* タイプ：文字列
* 以下の場合に指定可能：action=addTag, modifyTag
* 省略可能


#### newTitle：追加・変更する項目のタイトル
* タイプ：文字列
* 以下の場合に指定：action=addItem, modifyItem
* action=addItem かつ newItemCode が指定されている場合、空白文字列もしくは省略が許される。この場合は Amazon 等から取ってきた情報が使用される。
* action=modifyItem かつ Amazon 等から取ってきた情報が存在する場合、空白文字が許される。この場合は Amazon 等から取ってきた情報にリセットされる。


#### newAuthor, newPublisher：追加・変更する項目の著者・出版社
* タイプ：文字列
* 以下の場合に指定可能：action=addItem, modifyItem
* action=addItem かつ newItemCode が指定されている場合で、空白文字列もしくは省略した場合 Amazon 等から取ってきた情報が使用される。
* action=addItem かつ newItemCode が指定されていない場合、空白文字列もしくは省略した場合には、アイテム情報が空欄になる。
* action=modifyItem かつ Amazon 等から取ってきた情報が存在する場合、空白文字が許される。この場合は Amazon 等から取ってきた情報にリセットされる。
* 省略可能


#### newMemo：追加・変更する項目のメモ
* タイプ：文字列
* 以下の場合に指定可能：action=addItem, modifyItem
* 省略可能

### deleteMemoFlag :メモを削除する場合に指定
* タイプ：任意の文字列
* 存在するかどうかのみがチェックされる
* newMemo がカラ文字列だと、メモを空にしたいのかそうでないのかが区別できないため、これでチェックしている
* 以下の場合に指定可能：action=modifyItem

