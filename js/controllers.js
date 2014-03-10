/* Controller */

var __baseURI = '/';
var audioportControllers = angular.module('audioportControllers', []);

audioportControllers.controller('AudioSearchCtrl', ['$scope','$http','$routeParams','$location','SongApi', 'AudioPlayer',
	function($scope,$http,$routeParams,$location,SongApi,AudioPlayer) {
		var search = function(query) {
			$scope.loading = true;
			SongApi.search(query, 0, function(status,result) {
				$scope.loading = false;
				if (status === true)  { 
					$scope.songs = result; 
					$scope.hasMore = $scope.songs.length >= 100;
				}
			});
			$scope.lastSearch = query;
		};
		
		// State variables
		$scope.songs = [];
		$scope.lastSearch = '';
		$scope.query = $routeParams.query || '';
		$scope.loading = false;
		$scope.appending = false;
		$scope.hasMore = false;

		$scope.search = function() {
			$location.path('/search/' + $scope.query);
		};
		
		$scope.append = function() {
			$scope.appending = true;
			SongApi.search($scope.lastSearch, $scope.songs.length, function(status,result) {
				$scope.appending = false;
				if (status === true) { 
					var len = $scope.songs.length;
					$scope.songs = $scope.songs.concat(result);
					$scope.hasMore = $scope.songs.length > len;				
				}
			});
		};
		
		if ($routeParams.query) {
			search($routeParams.query);
		}
	}
]);

audioportControllers.controller('AudioIndexCtrl', ['$scope','$location',
	function($scope,$location) {
		$scope.query = '';
		
		$scope.runQuery = function() {
			$location.path('/search/' + $scope.query);
		};
	}
]);