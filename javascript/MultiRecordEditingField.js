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
			result = result.replace(new RegExp('o-multirecordediting-id'.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&'), 'g'), variables.id);
			return result;
		}

		function getTemplate(name, html) {
			return _cache[name];
		}

		function setTemplate(name, html) {
			_cache[name] = html;
		}

		//
		// I/O
		//
		$('input.js-multirecordediting-add-inline, button.js-multirecordediting-add-inline').entwine({
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
					var id = 'new_'+num;

					if (!hasTemplate(className)) {
						setTemplate(className, data);
					}

					var $fieldset = $self.parent();
					$fieldset.find('.js-multirecordediting-insertpoint_'+fieldName).before(renderTemplate(className, { 
						id: id
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
				var fieldName = $self.data('name');
				var url = action+'/field/'+fieldName+'/addinlinerecord';
				url += '?ClassName=' + encodeURIComponent(className);

				if (!hasTemplate(className))
				{
					$self.addClass('is-loading');
					$.ajax({
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