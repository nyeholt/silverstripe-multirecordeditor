<div class="multirecordeditingfield-togglecompositefield js-multirecordediting-list-item" data-id="<% if $MultiRecordEditingFieldRecord %>$MultiRecordEditingFieldRecord.ID<% else %>$Parent.getFieldID(0)<% end_if %>">
	<% if $Parent.CanSort %>
		<div class="multirecordeditingfield-togglecompositefield_sort">
			<span class="multirecordeditingfield-togglecompositefield_sort_handle js-multirecordediting-sort-handle">
				<span class="multirecordeditingfield-togglecompositefield_sort_handle_icon">Sort</span>
			</span>
		</div>
	<% end_if %>
	<% include ToggleCompositeField %>
</div>