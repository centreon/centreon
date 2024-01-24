from robot.api import logger


def is_gorgone_finished(http_data):
    with open('/tmp/output.txt', 'a') as f:
        f.write('Hi\n')

    for elem in http_data["data"]:
        with open('/tmp/output.txt', 'a') as f:
            f.write('DATA : ' + elem["data"] + "\n")
        if "discovery finished" in elem["data"]:
            return 1
    return 0
