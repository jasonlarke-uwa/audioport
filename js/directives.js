/* Directives */

var audioportDirectives = angular.module('audioportDirectives', []);

audioportDirectives.directive('apSongInfo', ['AudioPlayer', function(AudioPlayer) {
	var _isMobile = window.IS_MOBILE_DEVICE || false;
	
	function link_impl(scope, element, attrs) {
		if (!_isMobile) {
			element.on('mouseover mouseout', function(evt) {
				scope.hovering = (evt.type.toLowerCase() === 'mouseover');
				scope.$apply();
			});
		}
		
		scope.toggleTrack = function() {
			if (AudioPlayer.getCurrentTrack() !== scope.song || !scope.song.isPlaying)
				AudioPlayer.play(scope.song)
			else
				AudioPlayer.pause();
		};
	}

	return {
		link : link_impl,
		templateUrl : (_isMobile ? 'partials/mobile/_songinfo.html' : 'partials/_songinfo.html'),
		scope : {
			song : '=',
			number : '='
		}
	};
}]);

audioportDirectives.directive('apControl', ['AudioPlayer', function(AudioPlayer) {
	
}]);

audioportDirectives.directive('apEllipsis', ['$interval', function($interval) {
	function link_impl(scope, element, attrs) {
		var me = attrs.ellipsis || 3;
		var speed = attrs.speed || 1000;
		var nadded = 1;
		var timeoutId = null;
		
		var checkTimer = function(visible) {
			if (visible) {
				timeoutId = $interval(update, speed);
			}
			else if (timeoutId !== null) {
				$interval.cancel(timeoutId);
				timeoutId = null;
			}
		};
		
		var update = function() {
			var txt = element.text();
			if (nadded > 0) {
				if (nadded <= me) { txt += '.'; }
				if (++nadded > me) { nadded = -1; }
			}
			else if (nadded < 0) {
				if (nadded >= -me) { txt = txt.substring(0, txt.length - 1); }
				if (--nadded < -me) { nadded = 1; }
			}
			element.text(txt);
		};
		
		element.on('$destroy', function() {
			checkTimer(false);
		});
		
		scope.$watch(function() { return element.hasClass('ng-hide') }, function(val) {
			checkTimer(!val);				
		});
		
		update();
	}
	
	return {
		restrict : 'A',
		link : link_impl
	};
}]);