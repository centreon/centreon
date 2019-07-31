export default (array) => {
	let result = [];
	if(array){
		for(let i = 0; i < array.length; i++){
			var separatedString = array[i].split(":");
			result.push({
				id:separatedString[0],
				name:separatedString[1]
			})
		}
	}
	return result;
  }