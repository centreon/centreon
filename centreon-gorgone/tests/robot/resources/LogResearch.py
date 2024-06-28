from robot.api import logger
import re
import time
from dateutil import parser
from datetime import datetime

TIMEOUT = 30


def ctn_find_in_log_with_timeout(log: str, content, timeout=20, date=-1, regex=False):
    """! search a pattern in log from date param
        @param log: path of the log file
        @param date: date from witch it begins search, you might want to use robot Get Current Date function
        @param content: array of pattern to search
        @param timeout: time out in second
        @param regex: search use regex, default to false
        @return  True/False, array of lines found for each pattern
        """
    if date == -1:
        date = datetime.now().timestamp() - 1
    limit = time.time() + timeout
    c = ""
    while time.time() < limit:
        ok, c = ctn_find_in_log(log, date, content, regex)
        if ok:
            return True, c
        time.sleep(5)
    logger.console(f"Unable to find '{c}' from {date} during {timeout}s")
    return False


def ctn_find_in_log(log: str, date, content, regex=False):
    """Find content in log file from the given date

    Args:
        log (str): The log file
        date (_type_): A date as a string
        content (_type_): An array of strings we want to find in the log.

    Returns:
        boolean,str: The boolean is True on success, and the string contains the first string not found in logs otherwise.
    """
    logger.info(f"regex={regex}")
    res = []

    try:
        f = open(log, "r", encoding="latin1")
        lines = f.readlines()
        f.close()
        idx = ctn_find_line_from(lines, date)

        for c in content:
            found = False
            for i in range(idx, len(lines)):
                line = lines[i]
                if regex:
                    match = re.search(c, line)
                else:
                    match = c in line
                if match:
                    logger.console(f"\"{c}\" found at line {i} from {idx}")
                    found = True
                    res.append(line)
                    break
            if not found:
                return False, c

        return True, res
    except IOError:
        logger.console("The file '{}' does not exist".format(log))
        return False, content[0]


def ctn_extract_date_from_log(line: str):
    p = re.compile(r"(^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})")
    m = p.match(line)
    if m is None:
        return None
    try:
        return parser.parse(m.group(1))
    except parser.ParserError:
        logger.console(f"Unable to parse the date from the line {line}")
        return None


def ctn_find_line_from(lines, date):
    try:
        my_date = parser.parse(date)
    except:
        my_date = datetime.fromtimestamp(date)

    # Let's find my_date
    start = 0
    end = len(lines) - 1
    idx = start
    while end > start:
        idx = (start + end) // 2
        idx_d = ctn_extract_date_from_log(lines[idx])
        while idx_d is None:
            logger.console("Unable to parse the date ({} <= {} <= {}): <<{}>>".format(
                start, idx, end, lines[idx]))
            idx -= 1
            if idx >= 0:
                idx_d = ctn_extract_date_from_log(lines[idx])
            else:
                logger.console("We are at the first line and no date found")
                return 0
        if my_date <= idx_d and end != idx:
            end = idx
        elif my_date > idx_d and start != idx:
            start = idx
        else:
            break
    return idx
