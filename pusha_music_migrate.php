<?php

$worker= new GearmanWorker();
$worker->addServer();
$worker->addFunction("migrate_playlist_to_kz", "migrate_playlist_to_kz");
while ($worker->work());

function migrate_playlist_to_kz($job) {
  require_once 'HTTP/Request2.php';

  $config = yaml_parse(file_get_contents("config.yml"));
  $request = new HTTP_Request2($config["get_playlist_url"]);
  $request->setMethod(HTTP_Request2::METHOD_GET);
  $url = $request->getUrl();
  $url->setQueryVariables(array(
    'id'  => $job->workload(),
    'q3h' => 1
  ));
  $json = $request->send()->getBody();
  $playlist = json_decode($json);

  if (empty($playlist)) {
    echo "Failed to get platylist\n";
    return;
  }
  if (empty($config["music_path"])) {
    die("music_path is not defined.\n");
  }
  system("rm -rf {$config["music_path"]}/*");
  $playlist->name = preg_replace("/[^\ 0-9a-zA-Zа-яА-Я]/u", "", $playlist->name);
  $playlist_dir = "{$config["music_path"]}/{$playlist->name}";
  mkdir($playlist_dir);
  if (!empty($playlist->text)) {
    file_put_contents("{$playlist_dir}/info.txt", $playlist->text);  
  }
  if ($playlist->cover > 0) {
    system("cd \"{$playlist_dir}\"; wget http://download.files.namba.kg/files/{$playlist->cover}/cover.jpg");
  }  
  foreach ($playlist->files as $file) {
    system("wget \"http://download.files.namba.kg/files/{$file->id}/{$file->filename}?{$file->key}\" -O \"{$playlist_dir}/{$file->filename}\"");
  }
  system("php pusha_music.php");
}
?>
