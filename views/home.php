<article id="mainwelcome" class="fullpage">
  <h1>PHPHT</H1>
  <div class="container">
    <div class="mainblurb">
      <div>It's working!</div>
      <hr/>
    </div>
    <div class="subblurb">PHPHT is a lean, secure, Progressive Web App framework using Vue.js, Delight Auth and Mailjet</div>
    <div id="learnmore">
      <a href="<?php echo $this->getConfig("prefixurl") ?>/blog/" class="buttonstyle compact">Learn more</a>
    </div>
  </div>
  <div class="container wimpy">
    <div class="logobox">
      <img id="applogo" src="<?php echo $this->getConfig("prefixurl") ?>/public/img/phpht.png"/>
    </div>
  </div>
</article>
<?php
  setMessages("errors",$data);
  setMessages("messages",$data);
?>
