<div class="multirecordeditingfield-togglecompositefield js-multirecordediting-list-item">
	<% if $MultiRecordEditingField.CanSort %>
		<div class="multirecordeditingfield-togglecompositefield_sort">
			<span class="multirecordeditingfield-togglecompositefield_sort_handle js-multirecordediting-sort-handle">
				<span class="multirecordeditingfield-togglecompositefield_sort_handle_icon">Sort</span>
			</span>
		</div>
	<% end_if %>
	<% include ToggleCompositeField %>
</div>