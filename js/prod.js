var audioportApp=angular.module("audioportApp",["ngRoute","ngAnimate","audioportControllers","audioportFactories","audioportDirectives"]);audioportApp.config(["$routeProvider",function(a){var b=window.IS_MOBILE_DEVICE||!1;a.when("/search/:query",{templateUrl:b?"partials/mobile/_search.html":"partials/_search.html",controller:"AudioSearchCtrl"}).otherwise({templateUrl:b?"partials/mobile/_begin.html":"partials/_begin.html",controller:"AudioIndexCtrl"})}]);
var BASE_URI="/",SEARCH_API=BASE_URI+"ajax/search.php",INFO_API=BASE_URI+"ajax/getinfo.php",audioportFactories=angular.module("audioportFactories",[]),Song=function(a){a=a||{};for(var b in a)a.hasOwnProperty(b)&&(this[b]="string"===typeof a[b]?a[b].trim():a[b]);this.isPaused=this.isPlaying=!1};Song.enableLookup=!0;Song.lookupDefaults={genre:"Genre information is unavailable",album:"Album information is unavailable",year:"N/A",track:"N/A"};
Song.prototype.getStreamUrl=function(){return BASE_URI+"stream.php?rel=stream&id="+this.id+"&src="+encodeURIComponent(this.url)};Song.prototype.getDownloadUrl=function(){return BASE_URI+"stream.php?rel=download&id="+this.id+"&src="+encodeURIComponent(this.url)+"&name="+encodeURIComponent(this.artist)+" - "+encodeURIComponent(this.title)};
Song.prototype.getInformation=function(a){if(!this.cached&&Song.enableLookup){var b=INFO_API+"?id="+encodeURIComponent(this.id)+"&url="+encodeURIComponent(this.url)+"&artist="+encodeURIComponent(this.artist)+"&title="+encodeURIComponent(this.title),c=this;a.get(b).success(function(a){a.hasOwnProperty("success")&&!0===a.success?c.setInformation(a.data):c.setDefaultInformation();c.cached=!0}).error(function(a,b){c.setDefaultInformation();c.cached=!0})}else for(b in Song.lookupDefaults)Song.lookupDefaults.hasOwnProperty(b)&&
(this[b]=this[b]||Song.lookupDefaults[b]);return this};Song.prototype.setInformation=function(a){for(var b in Song.lookupDefaults)if(Song.lookupDefaults.hasOwnProperty(b)){var c=a[b]||Song.lookupDefaults[b];this[b]=this[b]||c.trim()}return this};Song.prototype.setDefaultInformation=function(){return this.setInformation({})};
audioportFactories.factory("SongApi",["$http",function(a){return{search:function(b,c,d){a.get(SEARCH_API+"?offset="+(c||0)+"&query="+encodeURIComponent(b)).success(function(b){var c=[];if(b.hasOwnProperty("success")&&!0===b.success){for(var e=0,g=b.data.songs.length;e<g;++e)c.push((new Song(b.data.songs[e])).getInformation(a));d(!0,c)}d(!1,b.hasOwnProperty("errors")?b.errors:["Unknown error occurred"])}).error(function(a,b){d(!1,["Unknown error occurred"])})}}}]);
audioportFactories.factory("AudioPlayer",function(){var a=null,b=new Audio||{};b.preload=!0;b.autoplay=!0;b.addEventListener("ended",function(){null!==a&&(a.isPlaying=!1)});return{play:function(c){null!==c&&a!==c?(null!==a&&(a.isPlaying=!1,a.isPaused=!1),a=c,b.src=a.getStreamUrl(),setTimeout(function(){b.play()},100),a.isPlaying=!0):b.paused&&(a.isPlaying=!0,a.isPaused=!1,b.play())},pause:function(){null!==a&&a.isPlaying&&(b.pause(),a.isPlaying=!1,a.isPaused=!0)},getCurrentTrack:function(){return a},
getPlayer:function(){return b}}});var audioportDirectives=angular.module("audioportDirectives",[]);
audioportDirectives.directive("apSongInfo",["AudioPlayer",function(a){var b=window.IS_MOBILE_DEVICE||!1;return{link:function(c,d,f){if(!b)d.on("mouseover mouseout",function(a){c.hovering="mouseover"===a.type.toLowerCase();c.$apply()});c.toggleTrack=function(){a.getCurrentTrack()===c.song&&c.song.isPlaying?a.pause():a.play(c.song)}},templateUrl:b?"partials/mobile/_songinfo.html":"partials/_songinfo.html",scope:{song:"=",number:"="}}}]);audioportDirectives.directive("apControl",["AudioPlayer",function(a){}]);
audioportDirectives.directive("apEllipsis",["$interval",function(a){return{restrict:"A",link:function(b,c,d){var f=d.ellipsis||3,h=d.speed||1E3,e=1,g=null,l=function(b){b?g=a(k,h):null!==g&&(a.cancel(g),g=null)},k=function(){var a=c.text();0<e?(e<=f&&(a+="."),++e>f&&(e=-1)):0>e&&(e>=-f&&(a=a.substring(0,a.length-1)),--e<-f&&(e=1));c.text(a)};c.on("$destroy",function(){l(!1)});b.$watch(function(){return c.hasClass("ng-hide")},function(a){l(!a)});k()}}}]);
var __baseURI="/",audioportControllers=angular.module("audioportControllers",[]);
audioportControllers.controller("AudioSearchCtrl",["$scope","$http","$routeParams","$location","SongApi","AudioPlayer",function(a,b,c,d,f,h){b=function(b){a.loading=!0;f.search(b,0,function(b,c){a.loading=!1;!0===b&&(a.songs=c,a.hasMore=100<=a.songs.length)});a.lastSearch=b};a.songs=[];a.lastSearch="";a.query=c.query||"";a.loading=!1;a.appending=!1;a.hasMore=!1;a.search=function(){d.path("/search/"+a.query)};a.append=function(){a.appending=!0;f.search(a.lastSearch,a.songs.length,function(b,c){a.appending=
!1;if(!0===b){var d=a.songs.length;a.songs=a.songs.concat(c);a.hasMore=a.songs.length>d}})};c.query&&b(c.query)}]);audioportControllers.controller("AudioIndexCtrl",["$scope","$location",function(a,b){a.query="";a.runQuery=function(){b.path("/search/"+a.query)}}]);