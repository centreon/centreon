from fastapi import FastAPI
import subprocess
import os
import signal

app = FastAPI()
cbd_proc = None

@app.on_event("startup")
def start_cbd():
    global cbd_proc
    cbd_proc = subprocess.Popen(
        ["/usr/sbin/cbwd", "/etc/centreon-broker/watchdog.json"],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, preexec_fn=os.setpgrp
    )

@app.post("/restart")
def restart_cbd():
    global cbd_proc
    # Stop only the process we started
    if cbd_proc is not None:
        cbd_proc.terminate()  # sends SIGTERM
        try:
            cbd_proc.wait(timeout=5)
        except Exception:
            pass
    # Start cbd detached
    cbd_proc = subprocess.Popen(
        ["/usr/sbin/cbwd", "/etc/centreon-broker/watchdog.json"],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, preexec_fn=os.setpgrp
    )
    return {
        "start_pid": cbd_proc.pid
    }

@app.post("/reload")
def reload_cbd():
    global cbd_proc
    if cbd_proc is not None:
        cbd_proc.send_signal(signal.SIGHUP)
        return {"reload": "sent SIGHUP"}
    return {"reload": "cbd not running"}
