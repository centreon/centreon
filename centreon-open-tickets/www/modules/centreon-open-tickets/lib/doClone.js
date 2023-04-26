
var $sheepit = new Array();

function delete_sheepit() {
    $sheepit = new Array();
}

function remove_sheepit(suffixid, count_add) {
    var i;

    $sheepit[suffixid].removeAllForms();

    for (i = 0; i < count_add; i++) {
        $sheepit[suffixid].addForm();
    }
}

function reload_sheepit(suffixid) {
    var count = jQuery("#clone-count-" + suffixid).data("clone-count-" + suffixid);
    var i = 0;

    if (count > 0) {
        $sheepit[suffixid].inject(jQuery("#clone-values-" + suffixid).data("clone-values-" + suffixid));
    }
}

function cloneResort(id) {
    jQuery('input[name^="clone_order_'+id+'_"]').each(function(idx, el) {
        jQuery(el).val(idx);
    });
}

function init_sheepit() {
    jQuery(".clonable").each(function(idx, el) {
       var suffixid = jQuery(el).attr('id');

       if ($sheepit[suffixid] === undefined) {
            $sheepit[suffixid] = jQuery(el).sheepIt({
               separator: '',
               allowRemoveLast: true,
               allowRemoveCurrent: true,
               allowRemoveAll: true,
               allowAdd: true,
               allowAddN: true,
               minFormsCount: 0,
               maxFormsCount: 40,
               continuousIndex: false, // Less buggy
               iniFormsCount: jQuery("#clone-count-" + suffixid).data("clone-count-" + suffixid),
               data: jQuery("#clone-values-" + suffixid).data("clone-values-" + suffixid)
            });
       }

       cloneResort(suffixid);
   });

   jQuery(".clonable").sortable(
    {
        handle: ".clonehandle",
        axis: "y",
        helper: "clone",
        opacity: 0.5,
        placeholder: "clone-placeholder",
        tolerance: "pointer",
        stop: function(event, ui) {
            cloneResort(jQuery(this).attr('id'));
        }
    }
   );
}
