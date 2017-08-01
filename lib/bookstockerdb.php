<?php

require_once(__DIR__ . '/function.php');


// ========== Bookstocker の DB 関連のクラス ==========
//
// singleton class として実装してある。
// new は行わない、getInstance でインスタンスを得て、それを操作する
//
// どの関数でも、致命的なエラーがあった時には Exception を起こす。
//
// テーブル構造詳は ../doc/database.md を参照のこと
//
// 使用法 (引数等の情報は各APIの説明を参照のこと)
//
// ・初期化 (Singleton なので new は行わない)
//   $db = BookStockerDB::getInstance();             // エラー時NULLが帰る
//   $db->init(DB_DSN, DB_USERNAME, DB_PASSWORD);
//
// ・エラー情報を文字列で得る
//   $errString = $db->getErrorMessagesAndClear();
//
// ・場所(placeテーブル)を操作
//   $placeList = $db->getPlaceList();               // 二重連想配列が帰る
//   $db->addPlace($place);                          // エラー時FALSEが帰る
//   $db->deletePlace($id);                          // エラー時FALSEが帰る
//
// ・未読既読状態(stateテーブル)を操作
//   $stateList = $db->getStateList();               // 二重連想配列が帰る
//   $db->addState($state);                          // エラー時FALSEが帰る
//   $db->deleteState($id);                          // エラー時FALSEが帰る
//
// ・タグ(tagテーブル)を操作
//   $tagList = $db->getTagList();                   // 二重連想配列が帰る
//   $db->addTag($tag);                              // エラー時FALSEが帰る
//   $db->deleteTag($id);                            // エラー時FALSEが帰る
//
// ・タグ関連付け(tagrefテーブル)を操作
//   $tagList = $db->getTagRefList();                // 二重連想配列が帰る
//   $db->addTagRef($itemid, $tagid);                // エラー時FALSEが帰る
//   $db->deleteTagRef($itemid, $tagid)              // エラー時FALSEが帰る
//
// ・所持品(itemテーブル)を操作
//   $itemList = $db->searchItem($place, $state, $tag, $itemId, $itemCode, $title, $author, $publisher, $memo);
//                                                   // アイテム一覧を得る。二重連想配列が帰る
//                                                   // 各パラメータは配列であることに注意
//                                                   // tid, tag は、$tag を指定した場合のみに得られるので、必ず存在することを仮定しないこと
//   $db->addItem($datasource, $itemid, $title, $author, $publisher, $place, $state);
//                                                   // アイテム追加。エラー時FALSEが帰る。検索のために Amazon にあるアイテムでも $title 等を指定すること。
//                                                   // $datasource には BookStockerDB::DataSource_Amazon または BookStockerDB::DataSource_UserDefined を指定
//   $db->deleteItem($id);                           // エラー時FALSEが帰る
//   $db->modifyItemPlace($itemId, $placeId);
//   $db->modifyItemState($itemId, $stateId);
//   $db->modifyItemMemo($itemId, $memo);            // メモを消去したい場合は $memo を NULL にする
//   $num = $db->getLastItemId();                    // IDの最大値(＝最後に追加されたID)を返す
//
// ・サポート関数
//   BookStockerDB::makeStringParameter($str);       // $strにHTMLやSQL的にまずい文字が含まれている場合NULL、それ以外だと$strが帰る


class BookStockerDB
{
  private static $singletonInstance;

  private $dbh;
  private $errorMessages = [];


  // ---------- 定数 ----------
  const DataSource_Amazon = 1;
  const DataSource_UserDefined = 2;


  // ---------- Singleton 操作 ----------
  public static function getInstance()
  {
    if (!self::$singletonInstance)
    {
      self::$singletonInstance = new BookStockerDB;
    }
    return self::$singletonInstance;
  }



  // ---------- コンストラクタ等を private で封印 ----------
  final private function __construct() {}
  final private function __clone() {}



  // ---------- 初期化/終了。引数必須 ----------
  public function init($dbDsn, $dbUsername, $dbPassword)
  {
    $this->dbh = $this->openDb($dbDsn, $dbUsername, $dbPassword);
    if($this->dbh !== NULL)
    {
      $this->initDb($dbDsn);
      $this->initTable();
    }
  }



  public function __destruct()
  {
    $this->dbh = NULL;
    self::$singletonInstance = NULL;
  }



  // ---------- 内部関数：DBに接続 ----------
  private function openDb($dbDsn, $dbUsername, $dbPassword)
  {
    try {
      $dbDriverOptions = [];
      $this->dbh = new PDO($dbDsn, $dbUsername, $dbPassword, $dbDriverOptions);

    } catch(PDOException $e) {
      $this->dbh = NULL;
      $errmsg = $e->getMessage();
    }

    if($this->dbh === NULL)
    {
      throw new Exception("DB Connection Failed" . $errmsg);
    }

    return $this->dbh;
  }



  // ---------- 内部関数：DB設定 ----------
  private function initDb($dbDsn)
  {
    // SQLiteにおいて、外部制約の機能ををonにする
    if($this->dbh !== FALSE)
    {
      if(stristr($dbDsn, "sqlite") >= 0)
      {
        $this->dbh->exec("PRAGMA foreign_keys=ON");
      }
    }

    // 静的プレースフォルダを利用。PHP側ではなくDB側でプレースホルダを処理する
    $this->dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, FALSE);

    // SQL実行でエラーがあったときに黙ってる (execute時に毎回戻り値を確認)
    $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
  }



  // ---------- 内部関数：DB製作 ----------
  private function initTable()
  {
    if($this->dbh != NULL)
    {
      // 保存場所table
      $this->dbh->exec(
        "CREATE TABLE IF NOT EXISTS place (
          id    integer primary key autoincrement,
          place text not null unique
        )");

      // DBに何も入ってない場合に、最初の1個を入れる
      $preparedSql = $this->dbh->query("SELECT count(id) FROM place");
      $result = $preparedSql->fetch();

      if($result[0] == 0)
      {
        $preparedSql = $this->dbh->prepare("INSERT INTO place (place) values (:place)");
        $preparedSql->bindValue(":place", "未整理", PDO::PARAM_STR);
        $preparedSql->execute();

        $preparedSql = $this->dbh->prepare("INSERT INTO place (place) values (:state)");
        $preparedSql->bindValue(":state", "廃棄", PDO::PARAM_STR);
        $preparedSql->execute();
      }


      // 状態table
      $this->dbh->exec(
        "CREATE TABLE IF NOT EXISTS state (
          id    integer primary key autoincrement,
          state text not null unique
        )");

      // DBに何も入ってない場合に、最初の1個を入れる
      $preparedSql = $this->dbh->query("SELECT count(id) FROM state");
      $result = $preparedSql->fetch();

      if($result[0] == 0)
      {
        $preparedSql = $this->dbh->prepare("INSERT INTO state (state) values (:state)");
        $preparedSql->bindValue(":state", "未読", PDO::PARAM_STR);
        $preparedSql->execute();

        $preparedSql = $this->dbh->prepare("INSERT INTO state (state) values (:state)");
        $preparedSql->bindValue(":state", "既読", PDO::PARAM_STR);
        $preparedSql->execute();
      }


      // タグtable
      $this->dbh->exec(
        "CREATE TABLE IF NOT EXISTS tag (
          id  integer primary key autoincrement,
          tag text not null unique
        )");

      // DBに何も入ってない場合に、最初の1個を入れる
      $preparedSql = $this->dbh->query("SELECT count(id) FROM tag");
      $result = $preparedSql->fetch();

      if($result[0] == 0)
      {
        $preparedSql = $this->dbh->prepare("INSERT INTO tag (tag) values (:tag)");
        $preparedSql->bindValue(":tag", "1巻", PDO::PARAM_STR);
        $preparedSql->execute();

        $preparedSql = $this->dbh->prepare("INSERT INTO tag (tag) values (:tag)");
        $preparedSql->bindValue(":tag", "最終巻", PDO::PARAM_STR);
        $preparedSql->execute();
      }


      // タグ関連付けtable
      $this->dbh->exec(
        "CREATE TABLE IF NOT EXISTS tagref (
          item integer  not null,
          tag  integer  not null,
          FOREIGN KEY (item) REFERENCES item(id),
          FOREIGN KEY (tag) REFERENCES tag(id),
          PRIMARY KEY (item,tag)
        )");


      // 所持品table
      // datasource は、amazonアイテムの場合 DataSource_Amazon、ユーザが品名等を入れた場合 DataSource_UserDefined
      // ASINはアルファベットも含むため itemid は text である
      $this->dbh->exec(
        "CREATE TABLE IF NOT EXISTS item (
          id         integer primary key autoincrement,
          datasource integer not null,
          itemid     text unique,
          place      integer not null,
          state      integer not null,
          title      text unique,
          author     text,
          publisher  text,
          memo       text,
        FOREIGN KEY (place) REFERENCES place(id),
        FOREIGN KEY (state) REFERENCES state(id)
        )");
    }
  }



  // ---------- DBのエラー情報を文字列で得る ----------
  public function getErrorMessagesAndClear()
  {
    $msgs = $this->errorMessages;
    $this->errorMessages = [];
    return $msgs;
  }





  // ---------- SQLのplaceholderに渡す文字列パラメタを作る ----------
  public static function makeStringParameter($s)
  {
    $ret = NULL;
    if(!containsHtmlSqlSpecialCharactors($s))
    {
      $ret = mb_convert_encoding($s, "UTF-8", "UTF-8");  // マルチバイト文字が中途半端に途切れたものを削除
    }

    return $ret;
  }



  // ---------- placeを得る ----------
  public function getPlaceList()
  {
    if($this->dbh != NULL)
    {
      $preparedSql = $this->dbh->query("SELECT * FROM place ORDER BY id");
      $result = $preparedSql->fetchAll();
      return $result;
    }
    else
    {
      return [];
    }
  }



  // ---------- placeを追加 ----------
  // エラー時は FALSE が帰る。エラー内容は getErrorMessagesAndClear で得られる
  public function addPlace($place)
  {
    $ret = FALSE;
    $place = BookStockerDB::makeStringParameter($place);

    if($this->dbh != NULL && $place !== NULL)
    {
      $preparedSql = $this->dbh->prepare("INSERT INTO place (place) values (:place)");
      $preparedSql->bindValue(":place", (String)$place, PDO::PARAM_STR);
      $result = $preparedSql->execute();
      if($result == TRUE)
      {
        if($preparedSql->rowCount() == 1)
        {
          $ret = TRUE;
        }
      }
      else
      {
        $errInfo = $preparedSql->errorInfo();
        array_push($this->errorMessages, $errInfo[0] . ": " . $errInfo[1] . ": " . $errInfo[2]);
      }
    }

    return $ret;
  }



  // ---------- placeを削除 ----------
  // エラー時は FALSE が帰る。エラー内容は getErrorMessagesAndClear で得られる
  public function deletePlace($id)
  {
    $ret = FALSE;

    if($this->dbh != NULL && is_numeric($id))
    {
      $preparedSql = $this->dbh->prepare("DELETE FROM place WHERE id = :id");
      $preparedSql->bindValue(":id", (int)$id, PDO::PARAM_INT);
      $result = $preparedSql->execute();
      if($result == TRUE)
      {
        if($preparedSql->rowCount() == 1)
        {
          $ret = TRUE;
        }
      }
      else
      {
        $errInfo = $preparedSql->errorInfo();
        array_push($this->errorMessages, $errInfo[0] . ": " . $errInfo[1] . ": " . $errInfo[2]);
      }
    }

    return $ret;
  }




  // ---------- stateを得る ----------
  public function getStateList()
  {
    if($this->dbh != NULL)
    {
      $preparedSql = $this->dbh->query("SELECT * from state ORDER BY id");
      $result = $preparedSql->fetchAll();
      return $result;
    }
    else
    {
      return [];
    }
  }



  // ---------- stateを追加 ----------
  // エラー時は FALSE が帰る。エラー内容は getErrorMessagesAndClear で得られる
  public function addState($state)
  {
    $ret = FALSE;
    $state = BookStockerDB::makeStringParameter($state);

    if($this->dbh != NULL && $state !== NULL)
    {
      $preparedSql = $this->dbh->prepare("INSERT INTO state (state) values (:state)");
      $preparedSql->bindValue(":state", (String)$state, PDO::PARAM_STR);
      $result = $preparedSql->execute();
      if($result == TRUE)
      {
        if($preparedSql->rowCount() == 1)
        {
          $ret = TRUE;
        }
      }
      else
      {
        $errInfo = $preparedSql->errorInfo();
        array_push($this->errorMessages, $errInfo[0] . ": " . $errInfo[1] . ": " . $errInfo[2]);
      }
    }

    return $ret;
  }



  // ---------- stateを削除 ----------
  // エラー時は FALSE が帰る。エラー内容は getErrorMessagesAndClear で得られる
  public function deleteState($id)
  {
    $ret = FALSE;

    if($this->dbh != NULL && is_numeric($id))
    {
      $preparedSql = $this->dbh->prepare("DELETE FROM state WHERE id = :id");
      $preparedSql->bindValue(":id", (int)$id, PDO::PARAM_INT);
      $result = $preparedSql->execute();
      if($result == TRUE)
      {
        if($preparedSql->rowCount() == 1)
        {
          $ret = TRUE;
        }
      }
      else
      {
        $errInfo = $preparedSql->errorInfo();
        array_push($this->errorMessages, $errInfo[0] . ": " . $errInfo[1] . ": " . $errInfo[2]);
      }
    }

    return $ret;
  }



  // ---------- tagを得る ----------
  public function getTagList()
  {
    if($this->dbh != NULL)
    {
      $preparedSql = $this->dbh->query("SELECT * from tag ORDER BY id");
      $result = $preparedSql->fetchAll();
      return $result;
    }
    else
    {
      return [];
    }
  }



  // ---------- tagを追加 ----------
  // エラー時は FALSE が帰る。エラー内容は getErrorMessagesAndClear で得られる
  public function addTag($tag)
  {
    $ret = FALSE;
    $tag = BookStockerDB::makeStringParameter($tag);

    if($this->dbh != NULL && $tag !== NULL)
    {
      $preparedSql = $this->dbh->prepare("INSERT INTO tag (tag) values (:tag)");
      $preparedSql->bindValue(":tag", (String)$tag, PDO::PARAM_STR);
      $result = $preparedSql->execute();
      if($result == TRUE)
      {
        if($preparedSql->rowCount() == 1)
        {
          $ret = TRUE;
        }
      }
      else
      {
        $errInfo = $preparedSql->errorInfo();
        array_push($this->errorMessages, $errInfo[0] . ": " . $errInfo[1] . ": " . $errInfo[2]);
      }
    }

    return $ret;
  }



  // ---------- tagを削除 ----------
  // エラー時は FALSE が帰る。エラー内容は getErrorMessagesAndClear で得られる
  public function deleteTag($id)
  {
    $ret = FALSE;

    if($this->dbh != NULL && is_numeric($id))
    {
      $preparedSql = $this->dbh->prepare("DELETE FROM tag WHERE id = :id");
      $preparedSql->bindValue(":id", (int)$id, PDO::PARAM_INT);
      $result = $preparedSql->execute();
      if($result == TRUE)
      {
        if($preparedSql->rowCount() == 1)
        {
          $ret = TRUE;
        }
      }
      else
      {
        $errInfo = $preparedSql->errorInfo();
        array_push($this->errorMessages, $errInfo[0] . ": " . $errInfo[1] . ": " . $errInfo[2]);
      }
    }

    return $ret;
  }



  // ---------- itemidに対応したタグ一覧を得る ----------
  public function getTagRefList($itemid)
  {
    if($this->dbh != NULL && is_numeric($itemid))
    {
      $preparedSql = $this->dbh->query("SELECT tagref.tag AS tid, tag.tag AS tag FROM tagref INNER JOIN tag ON tagref.tag = tag.id WHERE tagref.item = :item ORDER BY tagref.tag");
      $preparedSql->bindValue(":item", (int)$itemid, PDO::PARAM_INT);
      $result = $preparedSql->execute();
      if($result == TRUE)
      {
        $result = $preparedSql->fetchAll();
        return $result;
      }
      else
      {
        $errInfo = $preparedSql->errorInfo();
        array_push($this->errorMessages, $errInfo[0] . ": " . $errInfo[1] . ": " . $errInfo[2]);
        return [];
      }
    }
    else
    {
      return [];
    }
  }



  // ---------- tagを追加 ----------
  // エラー時は FALSE が帰る。エラー内容は getErrorMessagesAndClear で得られる
  public function addTagRef($itemid, $tagid)
  {
    $ret = FALSE;

    if($this->dbh != NULL && is_numeric($itemid) && is_numeric($tagid))
    {
      $preparedSql = $this->dbh->prepare("INSERT INTO tagref (item, tag) values (:item, :tag)");
      $preparedSql->bindValue(":item", (int)$itemid, PDO::PARAM_INT);
      $preparedSql->bindValue(":tag",  (int)$tagid, PDO::PARAM_INT);
      $result = $preparedSql->execute();
      if($result == TRUE)
      {
        if($preparedSql->rowCount() == 1)
        {
          $ret = TRUE;
        }
      }
      else
      {
        $errInfo = $preparedSql->errorInfo();
        array_push($this->errorMessages, $errInfo[0] . ": " . $errInfo[1] . ": " . $errInfo[2]);
      }
    }

    return $ret;
  }



  // ---------- tagを削除 ----------
  // エラー時は FALSE が帰る。エラー内容は getErrorMessagesAndClear で得られる
  public function deleteTagRef($itemid, $tagid)
  {
    $ret = FALSE;

    if($this->dbh != NULL && is_numeric($itemid) && is_numeric($tagid))
    {
      $preparedSql = $this->dbh->prepare("DELETE FROM tagref WHERE item = :item AND tag = :tag");
      $preparedSql->bindValue(":item", (int)$itemid, PDO::PARAM_INT);
      $preparedSql->bindValue(":tag",  (int)$tagid, PDO::PARAM_INT);
      $result = $preparedSql->execute();
      if($result == TRUE)
      {
        if($preparedSql->rowCount() == 1)
        {
          $ret = TRUE;
        }
      }
      else
      {
        $errInfo = $preparedSql->errorInfo();
        array_push($this->errorMessages, $errInfo[0] . ": " . $errInfo[1] . ": " . $errInfo[2]);
      }
    }

    return $ret;
  }



  


  // ---------- 詳細検索 ----------
  public function searchItem($place = [], $state = [], $tag = [], $itemId = NULL, $itemCode = NULL, $title = NULL, $author = NULL, $publisher = NULL, $memo = NULL)
  {
    if($this->dbh != NULL)
    {

      $searchString = "";
      $fieldNameString = "";
      $innerJoinString = "";
      $placeHolderI = [];  // int型のplaceholderを保持
      $placeHolderS = [];  // String型のplaceholderを保持

      // まずは int 型の WHERE 句を先に作成
      if(isset($itemId) && is_array($itemId) && count($itemId) > 0)
      {
        $searchString .= " AND item.id in (";
        foreach($itemId as $a) { $searchString .= "?,"; }
        $searchString = rtrim($searchString, ",");
        $searchString .= ")";
        $placeHolderI = array_merge($placeHolderI, $itemId);
      }

      if(isset($place) && is_array($place) && count($place) > 0)
      {
        $searchString .= " AND place.id in (";
        foreach($place as $a) { $searchString .= "?,"; }
        $searchString = rtrim($searchString, ",");
        $searchString .= ")";
        $placeHolderI = array_merge($placeHolderI, $place);
      }

      if(isset($state) && is_array($state) && count($state) > 0)
      {
        $searchString .= " AND state.id in (";
        foreach($state as $a) { $searchString .= "?,"; }
        $searchString = rtrim($searchString, ",");
        $searchString .= ")";
        $placeHolderI = array_merge($placeHolderI, $state);
      }

      if(isset($tag) && is_array($tag) && count($tag) > 0)
      {
        $fieldNameString = ", tagref.tag AS tid, tag.tag AS tag";
        $innerJoinString = "INNER JOIN tagref ON item.id = tagref.item INNER JOIN tag ON tagref.tag = tag.id";
        $searchString .= " AND tagref.tag in (";
        foreach($tag as $a) { $searchString .= "?,"; }
        $searchString = rtrim($searchString, ",");
        $searchString .= ")";
        $placeHolderI = array_merge($placeHolderI, $tag);
      }

      // 次に String 型の  WHERE 句を作成
      if(isset($itemCode) && is_array($itemCode) && count($itemCode) > 0)
      {
        foreach ($itemCode as $a)
  	    {
      	  $searchString .= " AND itemid like ?";
      	}
        $placeHolderS = array_merge($placeHolderS, $itemCode);
      }

      if(isset($title) && is_array($title) && count($title) > 0)
      {
        foreach ($title as $a)
  	    {
      	  $searchString .= " AND title like ?";
      	}
        $placeHolderS = array_merge($placeHolderS, $title);
      }

      if(isset($author) && is_array($author) && count($author) > 0)
      {
        foreach ($author as $a)
  	    {
      	  $searchString .= " AND author like ?";
      	}
        $placeHolderS = array_merge($placeHolderS, $author);
      }

      if(isset($publisher) && is_array($publisher) && count($publisher) > 0)
      {
        foreach ($publisher as $a)
  	    {
      	  $searchString .= " AND publisher like ?";
      	}
        $placeHolderS = array_merge($placeHolderS, $publisher);
      }

      if(isset($memo) && is_array($memo) && count($memo) > 0)
      {
        foreach ($memo as $a)
  	    {
      	  $searchString .= " AND memo like ?";
      	}
        $placeHolderS = array_merge($placeHolderS, $memo);
      }

      // $searchString および $placeHolderI, $placeHolderS 完成
      if($searchString != "")
      {
        $searchString = "WHERE " . substr($searchString, 5);  // 最初の " AND " を除いたものを付ける
      }

      // SQL実行
      $preparedSql = $this->dbh->prepare(
        "SELECT item.id, datasource, itemid, place.id AS pid, place.place, state.id AS sid, state.state, title, author, publisher, memo " .
        $fieldNameString .
        " FROM item " .
        " INNER JOIN place ON item.place = place.id " .
        " INNER JOIN state ON item.state = state.id " .
        $innerJoinString . " " .
        $searchString .
        " ORDER BY item.id DESC;");

      for ($p=0; $p<count($placeHolderI);  $p++)
      {
        $preparedSql->bindValue(($p+1), (int)$placeHolderI[$p], PDO::PARAM_INT);
      }

      for ($q=0; $q<count($placeHolderS);  $q++)
      {
        $preparedSql->bindValue(($p+$q+1), (String)("%" . (String)$placeHolderS[$q] . "%"), PDO::PARAM_STR);
      }

      // 結果を得る
      $result = $preparedSql->execute();
      if($result == TRUE)
      {
        $result = $preparedSql->fetchAll();
      }
      else
      {
        $errInfo = $preparedSql->errorInfo();
        array_push($this->errorMessages, $errInfo[0] . ": " . $errInfo[1] . ": " . $errInfo[2]);
        $result = [];
      }
      return $result;
    }
    else
    {
      return [];
    }
  }



  // ---------- itemを追加 ----------
  // エラー時は FALSE が帰る。エラー内容は getErrorMessagesAndClear で得られる
  public function addItem($datasource, $itemid, $title, $author, $publisher, $place, $state)
  {
    $ret = FALSE;
    if(isset($itemid))    {  $itemid    = BookStockerDB::makeStringParameter($itemid);     }
    if(isset($title))     {  $title     = BookStockerDB::makeStringParameter($title);      }
    if(isset($author)   ) {  $author    = BookStockerDB::makeStringParameter($author);     }
    if(isset($publisher)) {  $publisher = BookStockerDB::makeStringParameter($publisher);  }

    if($this->dbh != NULL && is_numeric($datasource) &&  ($itemid !== NULL || $title !== NULL) && is_numeric($place) && is_numeric($state))
    {
      $preparedSql = $this->dbh->prepare("INSERT INTO item (datasource, itemid, title, author, publisher, place, state) " .
                                         "values (:datasource, :itemid, :title, :author, :publisher, :place, :state)");

      $preparedSql->bindValue(":datasource",  (int)$datasource,   PDO::PARAM_INT);

      if($itemid !== NULL)
      {
        $preparedSql->bindValue(":itemid",    (String)$itemid,    PDO::PARAM_STR);
      } else {
        $preparedSql->bindValue(":itemid",    NULL,               PDO::PARAM_NULL);
      }

      if($title !== NULL)
      {
        $preparedSql->bindValue(":title",     (String)$title,     PDO::PARAM_STR);
      } else {
        $preparedSql->bindValue(":title",     NULL,               PDO::PARAM_NULL);
      }

      if($author !== NULL)
      {
        $preparedSql->bindValue(":author",    (String)$author,    PDO::PARAM_STR);
      } else {
        $preparedSql->bindValue(":author",    NULL,               PDO::PARAM_NULL);
      }

      if($publisher !== NULL)
      {
        $preparedSql->bindValue(":publisher", (String)$publisher, PDO::PARAM_STR);
      } else {
        $preparedSql->bindValue(":publisher", NULL,               PDO::PARAM_NULL);
      }

      $preparedSql->bindValue(  ":place",     (int)$place,        PDO::PARAM_INT);
      $preparedSql->bindValue(  ":state",     (int)$state,        PDO::PARAM_INT);


      $result = $preparedSql->execute();
      if($result == TRUE)
      {
        if($preparedSql->rowCount() == 1)
        {
          $ret = TRUE;
        }
      }
      else
      {
        $errInfo = $preparedSql->errorInfo();
        array_push($this->errorMessages, $errInfo[0] . ": " . $errInfo[1] . ": " . $errInfo[2]);
      }
    }

    return $ret;
  }



  // ---------- itemを削除 ----------
  // エラー時は FALSE が帰る。エラー内容は getErrorMessagesAndClear で得られる
  public function deleteItem($id)
  {
    $ret = FALSE;

    if($this->dbh != NULL && is_numeric($id))
    {
      // まずはtagrefを削除
      $preparedSql1 = $this->dbh->prepare("DELETE FROM tagref WHERE item = :item");
      $preparedSql1->bindValue(":item", (int)$id, PDO::PARAM_INT);
      $result = $preparedSql1->execute();
      if($result == TRUE)
      {
        // itemを削除
        $preparedSql2 = $this->dbh->prepare("DELETE FROM item WHERE id = :id");
        $preparedSql2->bindValue(":id", (int)$id, PDO::PARAM_INT);
        $result = $preparedSql2->execute();
        if($result == TRUE)
        {
          if($preparedSql2->rowCount() == 1)
          {
            $ret = TRUE;
          }
        }
        else
        {
          $errInfo = $preparedSql2->errorInfo();
          array_push($this->errorMessages, $errInfo[0] . ": " . $errInfo[1] . ": " . $errInfo[2]);
        }
      }
      else
      {
        $errInfo = $preparedSql1->errorInfo();
        array_push($this->errorMessages, $errInfo[0] . ": " . $errInfo[1] . ": " . $errInfo[2]);
      }
    }

    return $ret;
  }



  // ---------- item情報の変更 ----------
  // エラー時は FALSE が帰る。エラー内容は getErrorMessagesAndClear で得られる
  public function modifyItemPlace($itemId, $placeId)
  {
    $ret = FALSE;

    if($this->dbh != NULL && is_numeric($itemId) && is_numeric($placeId))
    {
      $preparedSql = $this->dbh->prepare("UPDATE item SET place = :place WHERE id = :id");
      $preparedSql->bindValue(":place", (int)$placeId, PDO::PARAM_INT);
      $preparedSql->bindValue(":id",    (int)$itemId,  PDO::PARAM_INT);
      $result = $preparedSql->execute();
      if($result == TRUE)
      {
        if($preparedSql->rowCount() == 1)
        {
          $ret = TRUE;
        }
      }
      else
      {
        $errInfo = $preparedSql->errorInfo();
        array_push($this->errorMessages, $errInfo[0] . ": " . $errInfo[1] . ": " . $errInfo[2]);
      }
    }

    return $ret;
  }


  public function modifyItemState($itemId, $stateId)
  {
    $ret = FALSE;

    if($this->dbh != NULL && is_numeric($itemId) && is_numeric($stateId))
    {
      $preparedSql = $this->dbh->prepare("UPDATE item SET state = :state WHERE id = :id");
      $preparedSql->bindValue(":state", (int)$stateId, PDO::PARAM_INT);
      $preparedSql->bindValue(":id",    (int)$itemId,  PDO::PARAM_INT);
      $result = $preparedSql->execute();
      if($result == TRUE)
      {
        if($preparedSql->rowCount() == 1)
        {
          $ret = TRUE;
        }
      }
      else
      {
        $errInfo = $preparedSql->errorInfo();
        array_push($this->errorMessages, $errInfo[0] . ": " . $errInfo[1] . ": " . $errInfo[2]);
      }
    }

    return $ret;
  }


  public function modifyItemMemo($itemId, $memo)
  {
    $ret = FALSE;
    $memo = BookStockerDB::makeStringParameter($memo);

    if($this->dbh != NULL && is_numeric($itemId))
    {
      $preparedSql = $this->dbh->prepare("UPDATE item SET memo = :memo WHERE id = :id");
      if($memo === NULL)
      {
        $preparedSql->bindValue(":memo", NULL, PDO::PARAM_NULL);
      }
      else
      {
        $preparedSql->bindValue(":memo", (String)$memo, PDO::PARAM_STR);
      }
      $preparedSql->bindValue(":id", (int)$itemId,  PDO::PARAM_INT);
      $result = $preparedSql->execute();
      if($result == TRUE)
      {
        if($preparedSql->rowCount() == 1)
        {
          $ret = TRUE;
        }
      }
      else
      {
        $errInfo = $preparedSql->errorInfo();
        array_push($this->errorMessages, $errInfo[0] . ": " . $errInfo[1] . ": " . $errInfo[2]);
      }
    }

    return $ret;
  }

  public function getLastItemId()
  {
    $num = NULL;

    if($this->dbh != NULL)
    {
      $preparedSql = $this->dbh->query("SELECT id FROM item ORDER BY id DESC LIMIT 1");
      $result = $preparedSql->execute();
      if($result == TRUE)
      {
        $result = $preparedSql->fetchAll();

        if(count($result) == 0)
        {
          // itemテーブルに項目なし
          $num = 0;
        }
        else
        {
          $num = $result[0]['id'];
        }
      }
    }

    return $num;
  }

}


?>
