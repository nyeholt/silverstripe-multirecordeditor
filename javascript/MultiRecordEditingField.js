(function($) {
	$.entwine('ss.multirecordediting', function($) {
		//
		// Template System
		//
		var _cache = {};

		function hasTemplate(name) {
			return false; // todo(jake): remove debug
			return (typeof _cache[name] !== 'undefined'); 
		}

		function renderString(string, variables) {
			for (var i = 0; i < variables.length; ++i)
			{
				var it = variables[i];
				var index = i + 1;
				for (var key in it)
				{
					string = string.replace(new RegExp('o-multirecordediting-'+index+'-'+key.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&'), 'g'), it[key]);
				}
			}
			return string;
		}

		function renderTemplate(name, variables) {
			var result = _cache[name];
			return renderString(result, variables);
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
					self.sortupdate();
				}

				this.parent().sortable({
					handle: '.js-multirecordediting-sort-handle',
					helper: helper,
					opacity: 0.7,
					start: start,
					update: update,
					stop: stop
				});
			},
			onsortupdate: function() {
				this.parent().children().each(function(index) {
					var sortValue = index + 1;
					// NOTE(Jake): Finds the .first() because otherwise it could set all unrelated
					//			   sort fields in nested MultiRecordEditingField's.
					var $sortField = $(this).find('.js-multirecordediting-sort-field').first();
					$sortField.val(sortValue.toString());
				});
			},
			onnextsort: function() {
				return this.parent().children().length + 1;
			}
		});

		// todo(jake): remove if unused
		$.ajaxPrefilter(function(options, originalOptions, jqXHR) {
			console.log(options.url);
		});

		// Replace o-multirecordediting-* template variable with value when
		// the <input type="hidden"> tag is created after a file is selected/uploaded
		$('.ss-uploadfield-item-info input').entwine({
			onmatch: function() {
				this._super();
				var name = this.attr('name');
				if (name && name.indexOf('o-multirecordediting') > -1)
				{
					var renderTree = [];
					var $parents = $(this).parents('.js-multirecordediting-list-item');
					for (var i = $parents.length - 1; i >= 0; --i)
					{
						// NOTE(Jake): We want to iterate from top to bottom, so iterate in reverse. (not bottom-to-top)
						var $it = $($parents[i]);
						var subID = $it.data('id');
						renderTree.push({ 
							id: subID,
						});
					}
					var newName = renderString(name, renderTree);
					if (newName !== name)
					{
						$(this).attr('name', newName);
					}
				}
			},
			onunmatch: function() {
				this._super();
			}
		});

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
					var depth = $self.data('depth');

					//
					// Prepare the variables to replace in deeply nested field names, ie.
					// ie. ElementArea__MultiRecordEditingField__ElementGallery__o-multirecordediting-1-id__Images__MultiRecordEditingField__ElementGallery_Item__o-multirecordediting-2-id__Items__MultiRecordEditingField__ElementGallery_Item_Item__o-multirecordediting-3-id__Name
					// -becomes:-
					// ElementArea__MultiRecordEditingField__ElementGallery__new_1__Images__MultiRecordEditingField__ElementGallery_Item__new_1__Items__MultiRecordEditingField__ElementGallery_Item_Item__new_1__Name
					//
					var renderTree = [];
					var $parents = $self.parents('.js-multirecordediting-list-item');
					for (var i = $parents.length - 1; i >= 0; --i)
					{
						// NOTE(Jake): We want to iterate from top to bottom, so iterate in reverse. (not bottom-to-top)
						var $it = $($parents[i]);
						var subID = $it.data('id');
						var idParts = subID.toString().split('_');
						var sort = (idParts.length >= 2) ? idParts[1] : 0; // if subID = 'new_3', then sort = 3. If subID = '10', then don't bother with sort as its already set (ie. make it 0, who cares)
						renderTree.push({ 
							id: subID,
							sort: sort,
						});
					}
					renderTree.push({ 
						id: 'new_'+num,
						sort: num,
					});

					// Find field container ('js-multirecordediting-field') and add HTML
					// in the field list container.
					var $field = $self.parents('.js-multirecordediting-field').first();
					var $fieldList = $field.find('.js-multirecordediting-list').first();
					$fieldList.append(renderTemplate(className, renderTree));

					$self.data('add-inline-num', num + 1);
				});

				this._super();
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
							if (!hasTemplate(className)) {
								// todo(Jake): Ensure cache takes into account parent element tree (classes, specific IDs for parents, etc)
								setTemplate(className, data);
							}
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

				this._super();
			}
		});
	});
})(jQuery);