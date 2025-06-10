from fastapi import FastAPI
import subprocess
import os
import signal

app = FastAPI()
centengine_proc = None

@app.on_event("startup")
def start_centengine():
    global centengine_proc
    centengine_proc = subprocess.Popen(
        ["/usr/sbin/centengine", "/etc/centreon-engine/centengine.cfg"],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, preexec_fn=os.setpgrp
    )

@app.post("/restart")
def restart_centengine():
    global centengine_proc
    # Stop only the process we started
    if centengine_proc is not None:
        centengine_proc.terminate()  # sends SIGTERM
        try:
            centengine_proc.wait(timeout=5)
        except Exception:
            pass
    # Start centengine detached
    centengine_proc = subprocess.Popen(
        ["/usr/sbin/centengine", "/etc/centreon-engine/centengine.cfg"],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, preexec_fn=os.setpgrp
    )
    return {
        "start_pid": centengine_proc.pid
    }

@app.post("/reload")
def reload_centengine():
    global centengine_proc
    if centengine_proc is not None:
        centengine_proc.send_signal(signal.SIGHUP)
        return {"reload": "sent SIGHUP"}
    return {"reload": "centengine not running"}
