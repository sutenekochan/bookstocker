# データベース概要
* SQLite や MySQL を用い、蔵書データを管理している
* 実際に DB Access を行っているのは ../lib/bookstockerdb.php
* いちおう外部制約を付けてはあるが、SQLiteのバージョンによっては外部制約が機能しないので注意。DB の内容が不整合を起こしても何の対策もしていない
* indexは作らない (どうせ作るほどデータ入れないだろう)

# テーブル名
* place: 保存場所
* state: 未読既読等の状態
* item: 所持品

# placeテーブル
* id : integer primary key autoincrement : テーブル内のIDとして利用
* place : text not null unique : 保存場所の名前を保持。「未整理」「本棚」「Kindle」「廃棄」などが入る

# stateテーブル
* id : integer primary key autoincrement : テーブル内のIDとして利用
* state : text not null unique : 未読既読状態の名前を保持。「未読」「既読」「読むのをやめた」などが入る

# itemテーブル
* id : integer primary key autoincrement : テーブル内のIDとして利用
* datasource : integer not null : amazonアイテムの場合 BookStockerDB::DataSource_Amazon、ユーザが品名等を入れた場合 BookStockerDB::DataSource_UserDefined
* itemid : text unique : 商品コード。Amazonアイテムの場合ASINが入る。ASINはアルファベット('X'等)を含むことがあるため、形式はtextである。
* place : integer not null FOREIGN KEY (place) REFERENCES place(id) : 保存場所
* state : integer not null FOREIGN KEY (state) REFERENCES state(id) : 未読既読状態
* title : text unique : 商品名
* author : text :著者
* publisher : text : 出版社
* memo : text : メモ
* 運用として、itemid または title のどちらかが必須 (DB的な制約はつけていない)
* 運用として、Amazon から取ってきたアイテムの場合、XML から title 等を抜き出して title フィールド等に入れている。これは検索に使うための措置であり、title等が無い場合は検索に引っかからない (一覧表示はされる)
