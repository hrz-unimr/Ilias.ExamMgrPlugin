/**
 *  examManager -- ILIAS Plugin for the administration of e-assessments
 *  Copyright (C) 2015 Jasper Olbrich (olbrich <at> hrz.uni-marburg.de)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Jasper Olbrich (olbrich@hrz.uni-marburg.de)
 */


$(document).ready( function() {

    createRandomPW = function() {
        // [0-9A-Za-z] without look-alikes (1, I, i, j , l, 0, o, O, Q, 8, B)
        var chars = "2345679ACDEFGHJKLMNPRSTUVWXYZabcdefghkmnpqrstuvwxyz";
        var string_length = 8;
        var randomstring = '';
        for (var i=0; i<string_length; i++) {
            var pos = Math.floor(Math.random() * chars.length);
            randomstring += chars.charAt(pos);
        }
        $("#course_pw").val(randomstring);
    }

    var submit_non_default = function(evt) {
        var tgt = $(evt.target);
        var form = tgt.closest('form');
        var name = tgt.attr("name");
        form.append('<input type="hidden" name="' + name + '" value="noonecares" />');
        form.submit();
    };

    $(".delete_run_btn").bind('click', submit_non_default);
    $(".edit_run_btn").bind('click', submit_non_default);

    $(".unlink_course_btn").bind('click', submit_non_default);

    $(".cleanup_test_btn").bind('click', submit_non_default);
    $(".fetch_test_btn").bind('click', submit_non_default);
    $(".unlink_test_btn").bind('click', submit_non_default);

    $(".edit_room_btn").bind('click', submit_non_default);

    var select_range = function() {
        var start = $('input[name=checked_students_from]:checked');
        var end = $('input[name=checked_students_to]:checked');
        
        if($('input').index(start) > $('input').index(end)) {
            var tmp = end;
            end = start;
            start = tmp;
        }

        state = "pre";
        var check_if_in_range = function(index, elt) {  // close over start, end, state
            var elt = $(elt);
            elt.prop('checked', false);
            elt.closest('tr').removeClass('selectedRow');
            if(state == "pre" && elt.val() == start.val()) {
                state = "in";
            }
            if(state == "in") {
                elt.prop('checked', true);
                elt.closest('tr').addClass('selectedRow');
            }
            if (state == "in" && elt.val() == end.val()) {
                    state = "post";
            } 
        }

        $("input[name='checked_students[]']").each(check_if_in_range);

    };

    var select_delete_range = function(run) {
        var start = $('input[name=run_id_'+run+'_from]:checked');
        var end = $('input[name=run_id_'+run+'_to]:checked');
        
        if($('input').index(start) > $('input').index(end)) {
            var tmp = end;
            end = start;
            start = tmp;
        }

        state = "pre";
        var check_if_in_range = function(index, elt) {  // close over start, end, state
            var elt = $(elt);
            elt.prop('checked', false);
            elt.closest('td').removeClass('xemg_remove')
            elt.closest('td').prev().removeClass('xemg_remove')
            if(state == "pre" && elt.val() == start.val()) {
                state = "in";
            }
            if(state == "in") {
                elt.prop('checked', true);
                elt.closest('td').addClass('xemg_remove')
                elt.closest('td').prev().addClass('xemg_remove')
            }
            if (state == "in" && elt.val() == end.val()) {
                    state = "post";
            } 
        }

        $("input[name='run_id_" + run + "[]']:checkbox").each(check_if_in_range);

    };

    var select_one = function(evt) {
        var tgt = $(evt.target);
        tgt.closest('tr').toggleClass('selectedRow');
    };

    var select_delete_one = function(evt) {
        var tgt = $(evt.target);
        tgt.closest('td').toggleClass('xemg_remove');
        tgt.closest('td').prev().toggleClass('xemg_remove');
    }

    $('.checked_students_from').on('click', function(evt) {
        select_range();
    });

    $('.checked_students_to').on('click', function(evt) {
        select_range();
    });

    $('.checked_students').on('click', function(evt) {
        select_one(evt);
    });

    $('input[name^=run_id]:radio').on('click', function(evt) {
//        run_id = evt.target.
        var name = $(evt.target).attr('name');
        var runFinder = RegExp("run_id_(\\d+)");
        var run = runFinder.exec(name)[1];
        select_delete_range(run);
    });

    $('input[name^=run_id]:checkbox').on('click', function(evt) {
        select_delete_one(evt);
    });

});
