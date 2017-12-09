<div>
    <ul class="uk-breadcrumb">
        <li class="uk-active"><span>@lang('Marketplace')</span></li>
    </ul>
</div>

<div riot-view>

    <div if="{ ready }">

        <div class="uk-margin uk-clearfix" if="{ App.Utils.count(marketplace) }">

            <div class="uk-form-icon uk-form uk-text-muted">

                <i class="uk-icon-filter"></i>
                <input class="uk-form-large uk-form-blank" type="text" ref="txtfilter" placeholder="@lang('Filter Marketplace...')" onkeyup="{ updatefilter }">

            </div>


        </div>
        
        <div class="uk-grid">
            <div class="uk-width-1-4" each="{module, index in marketplace}">
                <div class="uk-panel uk-panel-box uk-panel-box-secondary" style="background:#fff;">
                    <div class="uk-panel-teaser">
                        <img src="{module.module_icon ? module.module_icon : 'http://placehold.it/300x150&text=No+Icon'}" style="width:300px;max-height:300px;" alt="">
                    </div>
                    <h3 class="uk-margin-bottom-remove">{module.module_name}</h3>
                    <small><a target="_blank" href="{github_uri}{module.module_repo}">{module.module_author ? '@' + module.module_author.replace('@', '') : '@somebody'}</a></small>

                    <hr>

                    <p><small>{module.module_description}</small></p>
                    <a class="uk-button uk-button-mini uk-button-primary" data-index="{index}" data-repo="{module.module_repo}" onclick="{ this.parent.install }">{install_label}</a>
                </div>
            </div>

        </div>

        <div class="uk-width-medium-1-1 uk-viewport-height-1-3 uk-container-center uk-text-center uk-flex uk-flex-middle uk-flex-center" if="{ !App.Utils.count(marketplace) }">



            <div class="uk-animation-scale">

                <p>
                    <img class="uk-svg-adjust uk-text-muted" src="@url('marketplace:icon.svg')" width="80" height="80" alt="Marketplace" data-uk-svg />
                </p>
                <hr>


                <span class="uk-text-large"><strong>@lang('Loading Marketplace').</strong>

                <p><small>Just a second.</small></p>

            </div>

        </div>


        

    </div>


    <script type="view/script">

        var $this = this;

        this.ready  = true;
        this.marketplace = {};
        this.github_uri = 'https://github.com/';


        this.on('mount', function() {

            App.callmodule('marketplace:get', true).then(function(data) {

                this.marketplace = data.result;
                this.ready  = true;
                this.install_label = 'Install';
                this.update();

            }.bind(this));
        });

        install(e) {
            var install_index = parseInt(e.target.getAttribute('data-index'));

            App.ui.confirm("Install this Module?", function() {

                $this.install_label = 'Installing..'; $this.update();

                App.request('/marketplace/install_module/'+install_index).then(function(data) {

                    if(data.success) {
                        App.ui.notify("Module Installed", "danger");
                        $this.install_label = 'Installed'; $this.update();
                    } else {
                        App.ui.notify("Could not install Module", "danger");
                        $this.install_label = 'Error!'; $this.update();
                    }
                });
                
            });

        }

        remove(e, region) {

            region = e.item.region;

            App.ui.confirm("Are you sure?", function() {

                App.request('/marketplace/remove_region/'+region, {nc:Math.random()}).then(function(data) {

                    App.ui.notify("Region removed", "success");

                    delete $this.marketplace[region];

                    $this.update();
                });
            });
        }

        updatefilter(e) {

        }

        infilter(region, value, name, label) {

            if (!this.refs.txtfilter.value) {
                return true;
            }

            value = this.refs.txtfilter.value.toLowerCase();
            name  = [region.name.toLowerCase(), region.label.toLowerCase()].join(' ');

            return name.indexOf(value) !== -1;
        }

    </script>

</div>
