
import DynamicComponentSource from './example2'; //TO:DO Replace with process.env.COMPONENT_SOURCE_PATH

if(window.parent){
    window.parent[process.env.COMPONENT_NAME] = DynamicComponentSource;
    var elem = window.parent.document;
    window.parent.compName = process.env.COMPONENT_NAME;
    var event = new CustomEvent(`component${process.env.COMPONENT_NAME}Loaded`);
    elem.dispatchEvent(event);
}