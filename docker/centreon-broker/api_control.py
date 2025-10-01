from fastapi import FastAPI
import subprocess
import os
import signal
import threading
import sys
import time

app = FastAPI()
cbd_proc = None
cbd_thread = None

def stream_output(proc):
    try:
        for raw in iter(proc.stdout.readline, b''):
            if not raw:
                break
            try:
                sys.stdout.write(raw.decode(errors="replace"))
            except Exception:
                sys.stdout.write(str(raw))
            sys.stdout.flush()
    except Exception:
        pass

@app.on_event("startup")
def start_cbd():
    global cbd_proc, cbd_thread
    cbd_proc = subprocess.Popen(
        ["/usr/sbin/cbd", "/etc/centreon-broker/central-broker.json"],
        stdout=subprocess.PIPE, stderr=subprocess.STDOUT, preexec_fn=os.setpgrp
    )
    cbd_thread = threading.Thread(target=stream_output, args=(cbd_proc,), daemon=True)
    cbd_thread.start()

@app.post("/restart")
def restart_cbd():
    global cbd_proc, cbd_thread
    if cbd_proc is not None:
        try:
            cbd_proc.terminate()
            cbd_proc.wait(timeout=5)
        except Exception:
            try:
                os.killpg(os.getpgid(cbd_proc.pid), signal.SIGKILL)
            except Exception:
                pass
    cbd_proc = subprocess.Popen(
        ["/usr/sbin/cbd", "/etc/centreon-broker/central-broker.json"],
        stdout=subprocess.PIPE, stderr=subprocess.STDOUT, preexec_fn=os.setpgrp
    )
    cbd_thread = threading.Thread(target=stream_output, args=(cbd_proc,), daemon=True)
    cbd_thread.start()
    return {"start_pid": cbd_proc.pid}

@app.post("/reload")
def reload_cbd():
    global cbd_proc
    if cbd_proc is not None:
        try:
            os.killpg(os.getpgid(cbd_proc.pid), signal.SIGHUP)
            return {"reload": "sent SIGHUP"}
        except Exception as e:
            return {"error": str(e)}
    return {"reload": "cbd not running"}

@app.on_event("shutdown")
def stop_cbd():
    global cbd_proc
    if cbd_proc is not None:
        try:
            cbd_proc.terminate()
            cbd_proc.wait(timeout=5)
        except Exception:
            try:
                os.killpg(os.getpgid(cbd_proc.pid), signal.SIGKILL)
            except Exception:
                pass
        cbd_proc = None
