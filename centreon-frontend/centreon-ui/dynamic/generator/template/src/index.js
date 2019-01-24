if(window.parent){
    import(/* webpackChunkName: "COMPONENT_CAPITALIZED_NAME" */ process.env.COMPONENT_SOURCE_PATH).then(Component => {
        window.parent[process.env.COMPONENT_NAME] = Component.default;
        var elem = window.parent.document;
        var event = elem.createEvent("Event");
        event.initEvent(`component${process.env.COMPONENT_NAME}Loaded`, false, true);
        elem.dispatchEvent(event);
    });
}