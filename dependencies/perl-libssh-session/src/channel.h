
enum ssh_channel_request_state_e {
	/** No request has been made */
	SSH_CHANNEL_REQ_STATE_NONE = 0,
	/** A request has been made and answer is pending */
	SSH_CHANNEL_REQ_STATE_PENDING,
	/** A request has been replied and accepted */
	SSH_CHANNEL_REQ_STATE_ACCEPTED,
	/** A request has been replied and refused */
	SSH_CHANNEL_REQ_STATE_DENIED,
	/** A request has been replied and an error happend */
	SSH_CHANNEL_REQ_STATE_ERROR
};

enum ssh_channel_state_e {
  SSH_CHANNEL_STATE_NOT_OPEN = 0,
  SSH_CHANNEL_STATE_OPENING,
  SSH_CHANNEL_STATE_OPEN_DENIED,
  SSH_CHANNEL_STATE_OPEN,
  SSH_CHANNEL_STATE_CLOSED
};

typedef struct ssh_channel_callbacks_struct *ssh_channel_callbacks;

struct ssh_channel_struct {
    ssh_session session; /* SSH_SESSION pointer */
    uint32_t local_channel;
    uint32_t local_window;
    int local_eof;
    uint32_t local_maxpacket;

    uint32_t remote_channel;
    uint32_t remote_window;
    int remote_eof; /* end of file received */
    uint32_t remote_maxpacket;
    enum ssh_channel_state_e state;
    int delayed_close;
    int flags;
    ssh_buffer stdout_buffer;
    ssh_buffer stderr_buffer;
    void *userarg;
    int version;
    int exit_status;
    enum ssh_channel_request_state_e request_state;
    ssh_channel_callbacks callbacks;
    /* counters */
    ssh_counter counter;
};
