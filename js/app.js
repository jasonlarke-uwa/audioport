/* APP */

var audioportApp = angular.module('audioportApp', [
  'ngRoute',
  'ngAnimate',
  'audioportControllers',
  'audioportFactories',
  'audioportDirectives'
]);

audioportApp.config(['$routeProvider', function($routeProvider) {
	var _isMobile = window.IS_MOBILE_DEVICE || false;
	
	$routeProvider.
	when('/search/:query', {
		templateUrl: (_isMobile ? 'partials/mobile/_search.html' : 'partials/_search.html'),
		controller: 'AudioSearchCtrl'
	}).
	otherwise({
		// index page by default
		templateUrl: (_isMobile ? 'partials/mobile/_begin.html' : 'partials/_begin.html'),
		controller: 'AudioIndexCtrl'
	});
}]);