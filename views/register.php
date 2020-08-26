<div class="container fullheight flex-centered">
  <div class="columns">
    <article id="registerpage" class="column col-12">
      <div class="columns">
        <form id="registerform" class="registerbox formbox column col-12 panel" action="<?php echo $this->getConfig("baseurl") ?>/register/" method="POST">
          <div class="panel-header text-center">
            <div class="panel-title">
              <figure class="avatar avatar-xl p-2">
                <i class="icon icon-emoji icon-3x"></i>
              </figure>
              <div class="text-large text-bold">Register</div>
            </div>
          </div>
          <div class="panel-nav">
          </div>
          <div class="panel-body">
            <div class="fieldset form-group">
              <div id="ff-email" class="formfield">
                <label class="form-label" for="email">Email</label>
                <input type="text" name="email" id="email" class="col-12 noLastPassStyle" placeholder="email" autocomplete="username" >
              </div>
              <div id="ff-pass" class="formfield">
                <label class="form-label" for="pass">Passphrase</label>
                <input type="password" name="pass" id="pass" class="col-12 noLastPassStyle" placeholder="pass phrase" autocomplete="new-password">
              </div>
            </div>
          </div>
          <div class="panel-footer">
            <div class="fieldset form-group">
              <div id="ff-login" class="formfield">
                <input type="submit" name="register-button" value="Register" class="btn btn-primary col-12" tabindex=0>
              </div>
            </div>
            <div class="fieldset form-group">
              <div class="ff-cancel" class="formfield">
                <a href="<?php echo $this->getConfig("baseurl") ?>/" type="submit" name="cancel-button" value="Cancel" class="btn btn col-12" tabindex=0>Cancel</a>
              </div>
            </div>
          </div>
        </form>
      </div>
    </article>
  </div>
</div>

