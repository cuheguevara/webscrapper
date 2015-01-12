<?php

//session_start();

function get_add_date($format = 'Y-m-d', $operator = '+ / -', $range = ' 30 ', $type = 'month|day|year') {
  //return date('c', strtotime(date("Y/m/d") . ' - 30 day'));
  return date($format, strtotime(date("Y/m/d") . ' ' . $operator . ' ' . $range . ' ' . $type));
}

function display_message($tipe, $url) {
  $message = "";

  switch ($tipe) {
    case '1':
      $message = "Validating URL : " . $url;
      break;
    case '2':
      $message = "Start Scrapping : " . $url;
      break;
    case '3':
      $message = "Checking Date : " . $url;
      break;
    case '4':
      $message = "Date Passed : " . $url;
      break;
    case '5':
      $message = "Trying to Open : " . $url;
      break;
    case '6':
      $message = "Scrapping Content : " . $url;
      break;
    case '98':
      $message = "Nothing To Save : " . $url;
      break;
    case '99':
      $message = "Failed to Open : " . $url;
      break;
  }
  return $message . "\n";
}

function get_pols($state_id = null) {
  if (is_null($state_id)) {
    $query = "select * from politicians";
  } else {
    $query = "select * from politicians where state_id = " . $state_id;
  }
  $data = array();

  $mysql = new mySql();
  $mysql->__connect();
  $mysql->execute($query);

  while ($pol = $mysql->getArray()) {
    array_push($data, $pol);
  }
  return $data;
}

function get_pdt_date_by_text($days = null) {
  $time_zone = new DateTimeZone('America/Los_Angeles');
  if (is_null($days)) {
    $date = new DateTime('yesterday', $time_zone);
  } else {
    $date = new DateTime($days, $time_zone);
  }

  return $date;
}

function get_pdt_date($days = null) {
  $time_zone = new DateTimeZone('America/Los_Angeles');
  if (is_null($days)) {
    $date = new DateTime('yesterday', $time_zone);
  } else {
    $date = new DateTime($days . ' days ago', $time_zone);
  }

  return $date;
}

function mongo_connect() {
  $mongo_host = "localhost";
  $connection = new MongoClient(); // connects to localhost:27017
  $connection = new MongoClient("mongodb://" . $mongo_host . ""); // connect to a remote host (default port: 27017)
  $connection = new MongoClient("mongodb://" . $mongo_host . ":27017"); // connect to a remote host at a given port

  return $connection;
}

function insert_articles_item_by_state($item, $state) {
  $pols = get_pols($state);
  $aliases = get_aliases($state);
  $arr_query = array();

  $con = mongo_connect();
  $collection = $con->fanitics->politician_articles;
  $cursor = $collection->find(array("title" => $item["title"]));

  if ($cursor->count() === 0) {
    try {
      $collection->insert($item);
      echo "Insert To MongoDB Success\n";
    } catch (Exception $e) {
      echo $e;
    }

    foreach ($pols as $pol) {
      $alias = $aliases[$pol["id"]];
      $mentions_count = 0;

      if ($alias[0] === "Barack Obama") {
        foreach ($alias as $aka) {
          if (!is_null($aka) || $aka !== "") {
            $mentions_count += substr_count($item["article"], $aka);
          }
        }
      } else {
        if (strpos($item["article"], $alias[0]) !== false) {
          foreach ($alias as $aka) {
            if (!is_null($aka) || $aka !== "") {
              $mentions_count += substr_count($item["article"], $aka);
            }
          }
        }
      }

      if ($mentions_count > 0) {
        array_push($arr_query, sprintf(
                        "(%s, '%s', '%s', '%s', %s, '%s', %s, %s, %s, %s)", $pol["id"], mysql_real_escape_string($item["title"]), mysql_real_escape_string($item["url"]), mysql_real_escape_string(date('Y-m-d', strtotime($item["date"]))), $item["news_source_id"], mysql_real_escape_string($item["article"]), 0, $mentions_count, time(), 0
                ));
      }
    }
  }

  echo insert_arr_to_mysql($arr_query);
}

function insert_arr_to_mysql($arr_query = array()) {
  if (sizeof($arr_query) === 0) {
    return "Empty Array \n";
  } else {
    $query = join(" , ", $arr_query);
    $query = "INSERT INTO news_mentions(politician_id, title, url, news_date, news_source_id, article_text, sentiment_score, mentions_count, whitli_id, sentiment_level) values " . $query;
    $mysql = new mySql();
    $mysql->__connect();
    return $mysql->execute($query);
  }
}

function get_aliases($state = null) {
  if (is_null($state)) {
    $query = "SELECT
                  politicians.id,
                  alias_name,
                  CASE politicians.matching_level WHEN \"Both word\" THEN CONCAT(first_name, ' ', last_name) WHEN \"First word\" THEN first_name WHEN \"Last word\" THEN last_name END as fullname,
                  IF(politicians.matching_level = \"Both word\", CONCAT(last_name, ', ', first_name), \"\") as another_fullname
              FROM aliases
              RIGHT JOIN politicians on politicians.id = politician_id
              order by politician_id";
  } else {
    $query = "SELECT
                  politicians.id,
                  alias_name,
                  CASE politicians.matching_level WHEN \"Both word\" THEN CONCAT(first_name, ' ', last_name) WHEN \"First word\" THEN first_name WHEN \"Last word\" THEN last_name END as fullname,
                  IF(politicians.matching_level = \"Both word\", CONCAT(last_name, ', ', first_name), \"\") as another_fullname
              FROM aliases
              RIGHT JOIN politicians on politicians.id = politician_id
              WHERE politicians.state_id = " . $state . " order by politician_id";
  }

  $mysql = new mySql();
  $mysql->__connect();
  $mysql->execute($query);

  $data = array();
  while ($row = $mysql->getArray()) {
    array_push($data, $row);
  }

  $key = "";
  $array = array();
  foreach ($data as $row) {
    if ($key !== $row["id"]) {
      $key = $row["id"];
    }

    if (!array_key_exists($key, $array)) {
      if ($row["another_fullname"] !== "") {
        $array[$key] = array($row["fullname"], $row["another_fullname"]);
      } else {
        $array[$key] = array($row["fullname"]);
      }
    }

    if (!is_null($row["alias_name"])) {
      array_push($array[$key], $row["alias_name"]);
    }
  }

  return $array;
}

function send_data_to_rrs($politician, $article_text, $post_id) {
  $data = array(
      "uid" => $politician["id"],
      "schema" => "generic",
      "profile_data" => array(
          "first_name" => $politician["first_name"],
          "last_name" => $politician["last_name"]
      ),
      "posts" => array(
          array(
              "created_time" => time(),
              "id" => $post_id . "_" . $politician["id"],
              "message" => $article_text
          )
      )
  );
  $url = "https://api.whit.li/user/importGeneric?api_key=rtdasp8xu8awasub75gxjty2";
  $requestHeaders = array(
      'Content-Type: application/json',
      'Accept: application/json',
      sprintf('Content-Length: %d', strlen(json_encode($data)))
  );

  $context = stream_context_create(
          array(
              'http' => array(
                  'method' => 'PUT',
                  'header' => implode("\r\n", $requestHeaders),
                  'content' => json_encode($data),
              )
          )
  );

  $response = file_get_contents($url, false, $context);
  $response_arr = json_decode($response, true);
  return $response_arr["status"];
}

function send_data_to_whit_li($politician, $article_text, $post_id) {
  $data = array(
      "uid" => $politician["id"],
      "schema" => "generic",
      "profile_data" => array(
          "first_name" => $politician["first_name"],
          "last_name" => $politician["last_name"]
      ),
      "posts" => array(
          array(
              "created_time" => time(),
              "id" => $post_id . "_" . $politician["id"],
              "message" => $article_text
          )
      )
  );
  $url = "https://api.whit.li/user/importGeneric?api_key=rtdasp8xu8awasub75gxjty2";
  $requestHeaders = array(
      'Content-Type: application/json',
      'Accept: application/json',
      sprintf('Content-Length: %d', strlen(json_encode($data)))
  );

  $context = stream_context_create(
          array(
              'http' => array(
                  'method' => 'PUT',
                  'header' => implode("\r\n", $requestHeaders),
                  'content' => json_encode($data),
              )
          )
  );

  $response = file_get_contents($url, false, $context);
  $response_arr = json_decode($response, true);
  return $response_arr["status"];
}

function find_date($string) {

  //Define month name:
  $month_names = array(
      "january",
      "february",
      "march",
      "april",
      "may",
      "june",
      "july",
      "august",
      "september",
      "october",
      "november",
      "december"
  );
  $string = strtolower($string);

  $month_number = $month = $matches_year = $year = $matches_month_number = $matches_month_word = $matches_day_number = "";

  //Match dates: 01/01/2012 or 30-12-11 or 1 2 1985
  preg_match('/([0-9]?[0-9])[\.\-\/ ]?([0-1]?[0-9])[\.\-\/ ]?([0-9]{2,4})/', $string, $matches);
  if ($matches) {
    if ($matches[1])
      $day = $matches[1];

    if ($matches[2])
      $month = $matches[2];

    if ($matches[3])
      $year = $matches[3];
  }

  //Match month name:
  preg_match('/(' . implode('|', $month_names) . ')/i', $string, $matches_month_word);

  if ($matches_month_word) {
    if ($matches_month_word[1])
      $month = array_search(strtolower($matches_month_word[1]), $month_names) + 1;
  }

  //Match 5th 1st day:
  preg_match('/([0-9]?[0-9])(st|nd|th)/', $string, $matches_day);
  if ($matches_day) {
    if ($matches_day[1])
      $day = $matches_day[1];
  }

  //Match Year if not already setted:
  if (empty($year)) {
    preg_match('/[0-9]{4}/', $string, $matches_year);
    if ($matches_year[0])
      $year = $matches_year[0];
  }
  if (!empty($day) && !empty($month) && empty($year)) {
    preg_match('/[0-9]{2}/', $string, $matches_year);
    if ($matches_year[0])
      $year = $matches_year[0];
  }

  //Leading 0
  if (1 == strlen($day))
    $day = '0' . $day;

  //Leading 0
  if (1 == strlen($month))
    $month = '0' . $month;

  //Check year:
  if (2 == strlen($year) && $year > 20)
    $year = '19' . $year;
  else if (2 == strlen($year) && $year < 20)
    $year = '20' . $year;

  $date = array(
      'year' => $year,
      'month' => $month,
      'day' => $day
  );

  //Return false if nothing found:
  if (empty($year) && empty($month) && empty($day))
    return false;
  else
    return $date;
}

function xml_attribute($object, $attribute) {
  if (isset($object[$attribute]))
    return (string) $object[$attribute];
}

?>
