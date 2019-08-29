const requiredValidator = (event, message) => new Promise((resolve, reject)=>{
	let value = event.target ? event.target.value : event;
	value.length > 0 ? resolve() : reject(message)
});
export {requiredValidator};