from fastapi import FastAPI
import subprocess
import os
import signal
import threading
import sys
import time

app = FastAPI()
centengine_proc = None
centengine_thread = None

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
def start_centengine():
    global centengine_proc, centengine_thread
    centengine_proc = subprocess.Popen(
        ["/usr/sbin/centengine", "/etc/centreon-engine/centengine.cfg"],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, preexec_fn=os.setpgrp
    )
    centengine_thread = threading.Thread(target=stream_output, args=(centengine_proc,), daemon=True)
    centengine_thread.start()

@app.post("/restart")
def restart_centengine():
    global centengine_proc, centengine_thread
    if centengine_proc is not None:
        try:
            centengine_proc.terminate()  # sends SIGTERM
            centengine_proc.wait(timeout=5)
        except Exception:
            try:
                os.killpg(os.getpgid(centengine_proc.pid), signal.SIGKILL)
            except Exception:
                pass
    centengine_proc = subprocess.Popen(
        ["/usr/sbin/centengine", "/etc/centreon-engine/centengine.cfg"],
        stdout=subprocess.PIPE, stderr=subprocess.STDOUT, preexec_fn=os.setpgrp
    )
    centengine_thread = threading.Thread(target=stream_output, args=(centengine_proc,), daemon=True)
    centengine_thread.start()
    return {"start_pid": centengine_proc.pid}

@app.post("/reload")
def reload_centengine():
    global centengine_proc
    if centengine_proc is not None:
        try:
            os.killpg(os.getpgid(centengine_proc.pid), signal.SIGHUP)
            return {"reload": "sent SIGHUP"}
        except Exception as e:
            return {"error": str(e)}
    return {"reload": "centengine not running"}

@app.on_event("shutdown")
def stop_centengine():
    global centengine_proc
    if centengine_proc is not None:
        try:
            centengine_proc.terminate()
            centengine_proc.wait(timeout=5)
        except Exception:
            try:
                os.killpg(os.getpgid(centengine_proc.pid), signal.SIGKILL)
            except Exception:
                pass
        centengine_proc = None
