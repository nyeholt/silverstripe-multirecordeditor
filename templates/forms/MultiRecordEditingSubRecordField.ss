<div class="multirecordeditingfield-subrecordfield js-multirecordediting-list-item" data-id="<% if $Record %>$Record.ID<% else %>$Parent.getFieldID(0)<% end_if %>">
	<% if $CanSort %>
		<div class="multirecordeditingfield-subrecordfield_sort">
			<span class="multirecordeditingfield-subrecordfield_sort_handle js-multirecordediting-sort-handle">
				<span class="multirecordeditingfield-subrecordfield_sort_handle_icon">Sort</span>
			</span>
		</div>
	<% end_if %>
	<div class="multirecordeditingfield-subrecordfield_fields">
		<% if $ToggleCompositeField %>
			<% with $ToggleCompositeField %>
				<%-- NOTE(Jake): Must ensure $FieldHolder is called so JS is loaded in for Frontend fields --%>
				$FieldHolder
			<% end_with %>
		<% else %>
			<% if $FieldList %>
				<% loop $FieldList %>
					$FieldHolder
				<% end_loop %>
			<% end_if %>
		<% end_if %>
	</div>
</div>