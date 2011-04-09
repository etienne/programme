$(document).ready(function(){
	// Make table headers clickable
	$('table.items th').hover(
		function() {
			$(this).addClass('hover');
		},
		function() {
			$(this).removeClass('hover');
		}
	).click(
		function() {
			location.href = $(this).children('a').attr('href');
		}
	)
	
	// Change table row behavior
	$('table.items td:not(.delete)').hover(
		function() {
			$(this).parents('tr').addClass('hover');
		},
		function() {
			$(this).parents('tr').removeClass('hover');
		}
	).click(
		function() {
			location.href = $(this).parents('tr').children('td:first-child').children('a').attr('href');
		}
	);
	
	/*
	// FCKEditor
	if ($('textarea.wysiwyg').length > 0) {
		$('textarea.wysiwyg').attr('id', 'wysiwyg').parents('div').width('560px');
		var oFCKeditor = new FCKeditor('wysiwyg');
		oFCKeditor.BasePath = "/qs/trunk/assets/js/fckeditor/";
		oFCKeditor.ReplaceTextarea() ;
	}*/
	
	
	// Add new selector for case-insensitive :contains(); code by Erik Beeson
	jQuery.extend(jQuery.expr[':'], {
		containsIgnoreCase: "(a.textContent||a.innerText||jQuery(a).text()||'').toLowerCase().indexOf((m[3]||'').toLowerCase())>=0"
	});
	
	// Add search field
	// $('table.items').before('<input type="search" class="search" />');
	
	function filterList() {
		filterText = $(this).attr('value');
		if (filterText.length > 0) {
			$('table.items tbody tr').css('display', 'none');
			$('table.items tbody tr:containsIgnoreCase('+ filterText +')').css('display', 'table-row');
		} else {
			$('table.items tbody tr').css('display', 'table-row');
		}
	}
	
	$('input.search').keyup(filterList);
	
 	function updateFieldVisibility() {
		$('.hidden').hide();
		$('select, :checked').each(function() {
			targetDiv = $('.' + $(this).attr('name') + $(this).val())
			if (targetDiv.length > 0) {
				targetDiv.show();
			}
		})
	}

	$('select, :checkbox').change(function() {
		updateFieldVisibility();
	})
	
	updateFieldVisibility();
	
});