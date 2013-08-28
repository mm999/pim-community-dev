/* global define */
define(['backbone', 'routing'],
function(Backbone, routing) {
    'use strict';

    /**
     * @export  oro/navigation/pagestate/model
     * @class   oro.navigation.pagestate.Model
     * @extends Backbone.Model
     */
    return Backbone.Model.extend({
        defaults: {
            restore   : false,
            pagestate : {
                pageId : '',
                data   : {}
            }
        },

        url: function(method) {
            var args = ['oro_api_put_pagestate'];
            if (this.id) {
                args.push({id: this.id});
            }
            return routing.generate.apply(routing.generate, args);
        }
    });
});
