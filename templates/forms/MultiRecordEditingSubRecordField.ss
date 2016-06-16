<div class="multirecordeditingfield-subrecordfield js-multirecordediting-list-item" data-id="<% if $Record %>$Record.ID<% else %>$Parent.getFieldID(0)<% end_if %>">
	<% if $ToggleCompositeField %>
		<% if $CanSort %>
			<div class="multirecordeditingfield-subrecordfield_sort">
				<span class="multirecordeditingfield-subrecordfield_sort_handle js-multirecordediting-sort-handle">
					<span class="multirecordeditingfield-subrecordfield_sort_handle_icon">Sort</span>
				</span>
			</div>
		<% end_if %>
		<div class="multirecordeditingfield-subrecordfield_fields">
			$ToggleCompositeField
		</div>
	<% else %>
		<div class="multirecordeditingfield-subrecordfield_fields">
			<% if $FieldList %>
				<% loop $FieldList %>
					$FieldHolder
				<% end_loop %>
			<% end_if %>
		</div>
	<% end_if %>
</div>