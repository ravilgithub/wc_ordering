;( function( $ ) {
	$( document ).ready( function() {

		// Смена типа отображения продуктов в "archiveproduct"
		var $gridListToggle = {
			setGridListClases: function( $this ) {
				var $tType = $this
								.parent()
									.siblings( 'li' )
										.find( 'a' )
											.removeClass( 'active' )
											.end()
										.end()
									.end()
								.addClass( 'active' )
								.data( 'toggle-type' );

				$( '.products' )
					.removeClass( 'grid list' )
					.addClass( $tType );

				/**
				* Описания установки "Cookie" - https://learn.javascript.ru/cookie
				*/

				var $date = new Date;
				$date.setDate( $date.getDate() + 14 );

				// Полный вариант куки
				//var $cookieStr = 'toggletype=' + $tType + '; expires=' + $date.toUTCString() + '; path=/; domain=mysite.com';

				var $cookieStr = 'toggletype=' + $tType + '; expires=' + $date.toUTCString() + '; path=/';

				document.cookie = $cookieStr;
				console.log( $cookieStr );
			},
			
			addGridListEvent: function() {
				var $self = this;
				$( '.toggler', '.grid-list-view' ).on( 'click', function( event ) {
					event.preventDefault();
					$self.setGridListClases( $( this ) );
				} );
			},

			getGridListCookie: function() {
				var $results = document.cookie.match( '(^|;) ?toggletype=([^;]*)(;|$)' );
				console.log( $results );

				if ( $results ) {
					var $el = $( '[data-toggle-type=' + $results[ 2 ] + ']', '.grid-list-view' );
					if ( $el.length > 0 ) {
						this.setGridListClases( $el );
					}
				}
			},

			init: function() {
				this.getGridListCookie();
				this.addGridListEvent();
			}
		};

		$gridListToggle.init();

	} );
} ) ( jQuery );
