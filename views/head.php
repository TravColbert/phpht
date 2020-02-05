<html>
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title><?php echo $pageTitle; ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://code.jquery.com/jquery-3.1.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/vue/dist/vue.js"></script>
  <link rel="stylesheet" type="text/css" media="screen" href="<?php echo $this->getConfig("prefixurl") ?>/public/css/phpht.css">
</head>
<?php
if(!$this->isLoggedIn()) {
?>
<body class="backgroundsplash">
<?php
} else {
?>
<body>
<?php
}

