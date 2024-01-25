def is_gorgone_finished(http_data):

    if 'error' in http_data.keys() and http_data["error"]:
        raise Exception("Found an error in gorgone response : " + http_data["error"])
    for elem in http_data["data"]:

        if 'data' in elem.keys():
            if 'cannot launch discovery' in elem['data']:
                raise Exception("gorgone can't launch discovery : " + http_data["data"])
            if elem["data"] and "discovery finished" in elem["data"]:
                return 1
    return 0
