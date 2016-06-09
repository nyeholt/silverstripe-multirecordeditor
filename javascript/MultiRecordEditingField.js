(function($) {
	$.entwine('ss', function($) {
		//
		// Template System
		//
		var _cache = {};

		function hasTemplate(name) {
			return false; // todo(jake): remove debug
			return (typeof _cache[name] !== 'undefined'); 
		}

		function renderTemplate(name, variables) {
			var result = _cache[name];
			for (var key in variables)
			{
				result = result.replace(new RegExp('o-multirecordediting-'+key.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&'), 'g'), variables[key]);
			}
			return result;
		}

		function getTemplate(name, html) {
			return _cache[name];
		}

		function setTemplate(name, html) {
			_cache[name] = html;
		}

		//
		// Sort
		//
		var isSorting = false;
		$('.js-multirecordediting-list-item').entwine({
			onadd: function() {
				var self = this;

				// Avoid accordions from ToggleCompositeField opening when
				// sorting.
				self.find('.ss-toggle').accordion({
					beforeActivate: function(e, data) {
						if (isSorting) { return false; }
					}
				});

				function helper(e, row) {
					return row.clone()
			          .addClass("is-helper")
			          .width(row.parent().width());
				}

				function start(e) {
					isSorting = true;
				}

				function update(e) {
					isSorting = false;
				}

				function stop(e) {
					self.parent().children().each(function(index) {
						var sortValue = index + 1;
						$(this).find('.js-multirecordediting-sort-field').val(sortValue);
					});
				}

				this.parent().sortable({
					handle: '.js-multirecordediting-sort-handle',
					helper: helper,
					opacity: 0.7,
					start: start,
					update: update,
					stop: stop
				});
			}
		});

		$.ajaxPrefilter(function(options, originalOptions, jqXHR) {
			console.log(options.url);
			// eg. /submit/CreateForm/field/ElementArea__ElementGallery__new_1__Images/addinlinerecord/ElementGallery_Item
			/*if (options.url.indexOf('__MultiRecordEditingField__') > -1)
			{
				var url = options.url;
				var dirParts = url.split('/');
				for (var i = 0; i < dirParts.length; ++i)
				{
					var dirPart = dirParts[i];
					if (dirPart.indexOf('__MultiRecordEditingField__') > -1)
					{
						var invalidAction = 
					}
				}
				console.log(options.url);
			}*/
		});

		//
		// I/O
		//
		$('input.js-multirecordediting-add-inline, button.js-multirecordediting-add-inline').entwine({
			onadd: function(e) {
				/*var $form = $(this[0].form);
				var formAction = $form.attr('action');
				console.log(formAction);
				$.ajaxPrefilter(function(options, originalOptions, jqXHR) {
					var formSegment = options.url.substr(0, formAction.length);
					if (formAction === formSegment)
					{
						console.log('rewrite this inline field: '+options.url);
					}
					console.log(options.url);
				});*/
			},
			onclick: function(e) {
				e.preventDefault();

				var self = this[0],
					$self = $(self);

				var className, $dropdown;
				$dropdown = $self.parent().parent().find('select.js-multirecordediting-classname');
				if ($dropdown.length) {
					className = $dropdown.val();
				} else {
					className = $self.data('class');
				}

				if (!className)
				{
					alert('Please select an item.');
					return;
				}

				var fieldName = $self.data('name');
				this.addinlinerecord(className, function(data) {
					var num = $self.data('add-inline-num') || 1;

					if (!hasTemplate(className)) {
						setTemplate(className, data);
					}

					var $parent = $self.parents('.js-multirecordediting-field');
					$parent.find('.js-multirecordediting-list').first().append(renderTemplate(className, { 
						id: 'new_'+num,
						sort: num,
					}));

					$self.data('add-inline-num', num + 1);
				});
			},
			addinlinerecord: function(className, callback) {
				var self = this[0];
				var $self = $(self);

				if ($self.hasClass('is-loading')) {
					return;
				}

				var $form = $(self.form);
				var action = $form.attr('action');
				var fieldAction = $self.data('action');
				var url = action+'/field/'+fieldAction+'/addinlinerecord';
				url += '/'+encodeURIComponent(className);

				if (!hasTemplate(className))
				{
					$self.addClass('is-loading');
					$.ajax({
						async: true,
						cache: false,
						url: url,
						success: function(data) {
							callback.apply(this, arguments);
						},
						error: function(xhr, status) {
							xhr.statusText = xhr.responseText;
						},
						complete: function() {
							$self.removeClass('is-loading');
						}
					});
				}
				else
				{
					var data = getTemplate(className);
					callback.apply(this, [data]);
				}
			}
		});
	});
})(jQuery);