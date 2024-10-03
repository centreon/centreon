--
-- Copyright 2023 Centreon
--
-- Licensed under the Apache License, Version 2.0 (the "License");
-- you may not use this file except in compliance with the License.
-- You may obtain a copy of the License at
--
--     http://www.apache.org/licenses/LICENSE-2.0
--
-- Unless required by applicable law or agreed to in writing, software
-- distributed under the License is distributed on an "AS IS" BASIS,
-- WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
-- See the License for the specific language governing permissions and
-- limitations under the License.
--
-- For more information : contact@centreon.com
--
local cURL = require "lcurl"

-- We want to use the Broker API version 2
broker_api_version = 2

-- The data used by this script. They can be replaced by a configuration from cbd.
local data = {
  base_url = "http://localhost/centreon/api/latest",
  aws_region = "eu-west-1",
  authfile = "",      -- Should give a json file with two entries: 'user' and 'password'.
                       -- If not defined it is ignored, and if it contains a wrong content,
                       -- it is also ignored. If authfile, user and password are filled, the
                       -- authfile content will overwrite user and password.
  user = "admin",
  password = "Centreon!2021",
  token = "",
  log_file = "/tmp/log",
  log_level = 0,           -- Set 3 to enable all the logs.
  refresh_delay = 500,     -- Delay in seconds before reloading a new notification configuration
  sender = "admin@centreon.com",
  mail_command = 'aws ses send-email --region {{AWS_REGION}} --from "{{SENDER}}" --bcc {{RECIPIENTS}} --subject "{{SUBJECT}}" --html "{{MESSAGE}}"',
  last_refresh = 0,        -- internal.
  current_uid = "unknown", -- internal
  host = {},               -- internal: the configuration raised by the API.
}

--- Get the user token from data.user/data.password. The token is stored into
-- data.token.
local function login()
  local c = cURL.easy{
    url = data.base_url .. "/login",
    post = true,
    postfields = '{ "security": {"credentials": {"login": "' .. string.gsub(data.user, '"', '\\"') .. '","password": "' .. string.gsub(data.password, '"', '\\"') .. '"}}}',
    writefunction = function (resp)
      local content = broker.json_decode(resp)
      if not content then
        broker_log:error(0, "Unable to get a new token, is it the good url '" .. tostring(data.base_url) .. "'?")
      else
        broker_log:info(1, "Got a new token")
        data.token = content.security.token
      end
    end,
  }

  ok, err = c:perform()
  if not ok then
    broker_log:error(0, "Unable to get a token for the '" .. data.user .. "' user")
  end
  c:close()
end

--- Function called when a response is get from the configuration api. As a
--  callback, it cannot be declared as local.
-- @param full A table with a string inside named resp. Initially it is empty and
--        when we have the full answer from the API, it is a full json result.
-- @param resp A string with some json inside. It may be complete or not.
function fill_conf(full, resp)
  full.resp = full.resp .. resp
end

--- Function called when a full response is get from the configuration api.
-- @param full_conf A json response message with the configuration.
local function update_conf(full_conf)
  broker_log:info(1, "Reading notification configuration: " .. tostring(full_conf))
  local content = broker.json_decode(full_conf)
  if not content then
    broker_log:error(0, "Unable to decode '" .. tostring(resp) .. "'. Is the URL still accessible?")
    return
  end
  local uid = content.uid
  if uid == data.current_uid then
    broker_log:info(1, "No change in the configuration, UID unchanged")
    return
  end

  broker_log:info(2, "Saving new UID")
  data.current_uid = uid

  data.host = {}
  for i,n in ipairs(content.result) do
    -- For each notification defined in result
    for ii,h in ipairs(n.hosts) do
      -- For each host defined in the notification
      local host_id = h.id
      if not data.host[host_id] then
        data.host[host_id] = { name = h.name, alias = h.alias, notification = {}}
      end
      local host_conf = data.host[host_id]
      local hnotif = { events = h.events, notification_id = n.notification_id, service = {} }
      host_conf.notification[#host_conf.notification + 1] = hnotif
      if #h.services > 0 then
        for iii,s in ipairs(h.services) do
          if s.events > 0 then
            -- Some notifications are configured on this service
            hnotif.service[s.id] = { name = s.name, alias = s.alias, events = s.events, notification_id = n.notification_id }
          end
        end
      end
    end
  end
  broker_log:info(2, "Configuration update done")
end

--- Try to get the configuration from the API. If the response code is 304,
--  the resources-UID is unchanged that means the configuration didn't change.
--  If the response code is 401, we have to login again.
--  If the response code is 200, we have an answer to parse to fill the cache
--  configuration in data.host.
local function get_configuration()
  local loop = true
  while loop do
    data.last_refresh = os.time()
    local c = cURL.easy{
      url = data.base_url .. "/configuration/notifications/resources",
      post = false,
      httpheader = {
        'Content-Type: application/json',
        'x-notifiable-resources-UID: ' .. tostring(data.current_uid),
        'x-AUTH-TOKEN: ' .. tostring(data.token),
      },
    }
    local full = { resp = "" }
    c:setopt_writefunction(fill_conf, full)
    ok, err = c:perform()
    if not ok then
      broker_log:error(0, "Unable to call the API to get the configuration.: " .. tostring(err))
    end
    local resp_code = c:getinfo(cURL.INFO_RESPONSE_CODE)
    broker_log:info(2, "Response code: " .. resp_code)
    if resp_code == 200 then
      update_conf(full.resp)
    end
    if resp_code == 401 then
      broker_log:info(1, "Expired token. Trying to get a new one")
      login()
    else
      loop = false
    end
    c:close()
  end

  if resp_code == 304 then
    broker_log:info(1, "No change in the configuration")
  elseif resp_code == 200 then
    broker_log:info(1, "Changes available in the configuration")
  end
end

--- Get the notification configuration from the API. The parameter is the
--  notification ID.
--  @param id The notification ID we know from the first API configuration.
--  @return A table with the notification content.
local function get_notification_rule(id)
  local content = {}
  local loop = true
  while loop do
    local c = cURL.easy{
      url = data.base_url .. "/configuration/notifications/" .. id .. "/rules",
      post = false,
      httpheader = {
        'Content-Type: application/json',
        'x-AUTH-TOKEN: ' .. tostring(data.token),
      },
      writefunction = function(resp)
        content = broker.json_decode(resp)
        if content then
          broker_log:info(2, resp)
        else
          broker_log:error(0, "Unable to decode the message '" .. tostring(resp) .. "'")
        end
      end,
    }
    ok, err = c:perform()
    if not ok then
      broker_log:error(0, "Unable to call the API to get the notification " .. id .. ": " .. tostring(err))
    end
    local resp_code = c:getinfo(cURL.INFO_RESPONSE_CODE)
    broker_log:info(2, "response code: " .. resp_code)
    if resp_code == 401 then
      broker_log:info(1, "Expired token. Trying to get a new one")
      login()
    else
      loop = false
    end
    c:close()
  end
  return content
end


--- Get the notification configuration from the API. The parameter is the
--  notification ID.
--  @param id The notification ID we know from the first API configuration.
--  @return A table with the notification content.
local function get_notification(id)
  local content = {}
  local loop = true
  while loop do
    local c = cURL.easy{
      url = data.base_url .. "/configuration/notifications/" .. id,
      post = false,
      httpheader = {
        'Content-Type: application/json',
        'x-AUTH-TOKEN: ' .. tostring(data.token),
      },
      writefunction = function(resp)
        content = broker.json_decode(resp)
        if content then
          broker_log:info(2, resp)
        else
          broker_log:error(0, "Unable to decode the message '" .. tostring(resp) .. "'")
        end
      end,
    }
    ok, err = c:perform()
    if not ok then
      broker_log:error(0, "Unable to call the API to get the notif " .. id .. ": " .. tostring(err))
    end
    local resp_code = c:getinfo(cURL.INFO_RESPONSE_CODE)
    broker_log:info(2, "response code: " .. resp_code)
    if resp_code == 401 then
      broker_log:info(1, "Expired token. Trying to get a new one")
      login()
    else
      loop = false
    end
    c:close()
  end
  return content
end

--- Get the time periods from the API.
--  @return A table with the time periods content.
local function get_time_period(id)
  local content = {}
  local loop = true
  while loop do
    local c = cURL.easy{
      url = data.base_url .. "/configuration/timeperiods/" .. id,
      post = false,
      httpheader = {
        'Content-Type: application/json',
        'x-AUTH-TOKEN: ' .. tostring(data.token),
      },
      writefunction = function(resp)
        content = broker.json_decode(resp)
        if content then
          broker_log:info(2, resp)
        else
          broker_log:error(0, "Unable to decode the message '" .. tostring(resp) .. "'")
        end
      end,
    }
    ok, err = c:perform()
    if not ok then
      broker_log:error(0, "Unable to call the API to get time period : " .. tostring(err))
    end
    local resp_code = c:getinfo(cURL.INFO_RESPONSE_CODE)
    broker_log:info(2, "response code: " .. resp_code)
    if resp_code == 401 then
      broker_log:info(1, "Expired token. Trying to get a new one")
      login()
    else
      loop = false
    end
    c:close()
  end
  return content
end

--- Fill a table with various key from the received event and the corresponding
--  resource configuration.
--  @param event A service status or a host status.
--  @param conf the resource configuration
--  @param hostname The hostname as a string only if event concerns a service.
--  @return A table that will be used to fill the notification message.
local function get_macros(event, conf, hostname)
  local notif_type = ""
  local states = {}
  local id = ""
  local name = tostring(conf.name)

  if event.state == 0 then
    notif_type = "RECOVERY"
  else
    notif_type = "PROBLEM"
  end

  -- PbServiceStatus
  if event._type == 65565 then
    states = { "OK", "WARNING", "CRITICAL", "UNKNOWN" }
    -- Real service or Anomalydetection
    if event.type == 0 or event.type == 4 then
      id = tostring(event.host_id) .. ":" .. tostring(event.service_id)
      name = tostring(hostname) .. '/' .. name
    -- Meta-service or BA
    elseif event.type == 2 or event.type == 3 then
      id = tostring(event.internal_id)
    end
  -- PbHostStatus
  elseif event._type == 65568 then
    states = { "UP", "DOWN", "UNREACHABLE" }
    id = tostring(event.host_id)
  end

  retval = {
    NOTIFICATIONTYPE = notif_type,
    NAME = name,
    STATE = tostring(states[event.state + 1]),
    ID = id,
    SHORTDATETIME = os.date("%x %X", event.last_hard_state_change),
    LONGDATETIME = os.date("%A %B %d, %Y, %X", event.last_hard_state_change),
    OUTPUT = tostring(event.output),
    ALIAS = tostring(conf.alias),
  }
  return retval
end

--- Replace the text macros given as {{key}} with their appropriate values
--  found in the table macros.
--  @param text The text where we have to make the changes
--  @param macros The table containing the key/value.
--  @return The resulting text.
local function replace_macros(text, macros)
  retval = text
  for k,v in pairs(macros) do
    broker_log:info(2, "replacing {{" .. k .. "}} by its value <<" .. v .. ">>")
    -- '%' is a specific character in gsub, we must escape it.
    local vv = string.gsub(v, "%%", "%%%%")
    retval = string.gsub(retval, "{{" .. k .. "}}", vv)
  end
  return retval
end

--- Send a notification by mail.
--  @param notif The notification configuration.
--  @param event The event received from broker (service status/host status)
--  @param conf The resource associated to the event.
--  @param hostname This is the hostname only given when the event is a
--                  ServiceStatus.
local function send_mail(notif, event, conf, hostname)
  local macros = get_macros(event, conf, hostname)
  local message = replace_macros(notif.formatted_message, macros)
  local subject = replace_macros(notif.subject, macros)

  -- Constructing the recipients string
  local recipients = ""
  for _,c in ipairs(notif.contacts) do
    local mail = c.email_address
    if #mail > 0 then
      if #recipients == 0 then
        recipients = mail
      else
        recipients = recipients .. " " .. mail
      end
    end
  end

  -- Protection of the strings.
  message = string.gsub(message, '"', '\\"')
  -- '%' is a specific character in gsub, we must escape it.
  message = string.gsub(message, "%%", "%%%%")
  subject = string.gsub(subject, '"', '\\"')
  -- '%' is a specific character in gsub, we must escape it.
  subject = string.gsub(subject, "%%", "%%%%")
  recipients = string.gsub(recipients, '"', '\\"')
  -- '%' is a specific character in gsub, we must escape it.
  recipients = string.gsub(recipients, "%%", "%%%%")
  sender = string.gsub(data.sender, '"', '\\"')
  -- '%' is a specific character in gsub, we must escape it.
  sender = string.gsub(sender, "%%", "%%%%")

  -- Constructing the mail command
  local cmd = data.mail_command
  cmd = string.gsub(cmd, "{{RECIPIENTS}}", recipients)
  cmd = string.gsub(cmd, "{{SENDER}}", sender)
  cmd = string.gsub(cmd, "{{MESSAGE}}", message)
  cmd = string.gsub(cmd, "{{SUBJECT}}", subject)

  broker_log:info(1, "command content: " .. cmd)
  -- Execution of the command
  local f = io.popen(cmd, 'r')
  if f then
    local s = f:read('*a')
    f:close()
    broker_log:info(0, "Sending notification -- output: " .. tostring(s))
  else
    broker_log:error(0, "Unable to get '" .. cmd .. "' output.")
  end
end

--- The init function of the stream connector. It is mandatory and must be not
-- declared as local.
-- @param conf A table where each key can be changed from Centreon WUI.
function init(conf)
  local unknown = ""
  local errors_count = 0
  for k,v in pairs(conf) do
    if data[k] then
      data[k] = v
    else
      errors_count = errors_count + 1
      if #unknown > 0 then
        unknown = unknown .. ", " .. k
      else
        unknown = k
      end
    end
  end

  broker_log:set_parameters(data.log_level, data.log_file)

  if type(data.authfile) == "string" and #data.authfile > 0 then
    local f = io.open(data.authfile, "r")
    if f then
      local content = f:read("*a")
      f:close()
      local j = broker.json_decode(content)
      if j.user and j.password then
        data.user = j.user
        data.password = j.password
      else
        broker_log:error(0, "The file '" .. data.authfile .. "' doesn't contain the good entries")
      end
    else
      broker_log:error(0, "The specified file '" .. data.authfile .. "' doesn't exist")
    end
  end
  if errors_count > 0 then
    if errors_count == 1 then
      broker_log:error(0, "Parameters " .. unknown ..
                          " is not recognized by the cloud notification stream connector")
    else
      broker_log:error(0, "Parameters " .. unknown ..
                          " are not recognized by the cloud notification stream connector")
    end
  end
  login()
  data.current_uid = "unknown"

  -- Initialization of the aws region
  if string.find(data.mail_command, "{{AWS_REGION}}") then
    local aws_region = data.aws_region
    if not aws_region or aws_region == "" then
      aws_region = "eu-west-1"
    end
    data.mail_command = string.gsub(data.mail_command, "{{AWS_REGION}}", aws_region)
  end
end

local function is_notifiable(notification)
    local notif = get_notification(notification.notification_id)
    local time_period = get_time_period(notif.timeperiod.id)
    return time_period.in_period == true
end

function write(d)
  local now = os.time()
  if now >= data.last_refresh + data.refresh_delay then
    broker_log:info(1, "Time to check new configuration")
    get_configuration()
  end

  -- PbServiceStatus
  if d._type == 65565 then
    -- Notification is done only on resources not in downtime and not acknowledged.
    if (d.scheduled_downtime_depth == 0 or d.scheduled_downtime_depth == nil) and (d.acknowledgement_type == 0 or d.acknowledgement_type == nil) then
      -- Look for the host containing this service in the notification configuration
      local host = data.host[d.host_id]
      if host then
        local hostname = host.name
        -- Looking for the service in the notification configuration
        for _, hnotif in ipairs(host.notification) do
          local svc = hnotif.service[d.service_id]
          if svc then
            broker_log:info(3, "Service (" .. d.host_id .. "," .. d.service_id ..
                               ") --- state: " .. d.state ..
                               " ; state_type: " .. d.state_type ..
                               " ; last_check: " .. d.last_check ..
                               " ; last_hard_state: " .. d.last_hard_state_change ..
                               " ; notif flags: " .. ((1 << d.state) & svc.events))
            -- We check that:
            --   the state matches with the notification configuration
            --   the state is HARD
            --   the change just occured.
            if ((1 << d.state) & svc.events) ~= 0 and d.state_type == 1 and d.last_check <= d.last_hard_state_change then
              broker_log:info(2, "Notification on service (" .. d.host_id .. "," .. d.service_id ..
                                 ") --- state: " .. d.state ..
                                 " ; notification_id: " .. svc.notification_id)
              local notif = get_notification_rule(svc.notification_id)
              if is_notifiable(notif) == false then
                goto continue
              end

              if notif and notif.channels and notif.channels.email then
                notif = notif.channels.email
                send_mail(notif, d, svc, hostname)
              end
            end
          end

          ::continue::
        end
      else
        broker_log:info(2, "host " .. d.host_id .. " not found")
      end
    else
      if d.scheduled_downtime_depth > 0 then
        broker_log:info(2, "Service (" .. d.host_id .. "," .. d.service_id .. ") in downtime, so no notification")
      elseif d.acknowledgement_type ~= 0 then
        broker_log:info(2, "Service (" .. d.host_id .. "," .. d.service_id .. ") acknowledged, so no notification")
      end
    end
  -- PbHostStatus
  elseif d._type == 65568 then
    -- Notification is done only on resources not in downtime and not acknowledged.
    if (d.scheduled_downtime_depth == 0 or d.scheduled_downtime_depth == nil) and (d.acknowledgement_type == 0 or d.acknowledgement_type == nil) then
      -- Looking for the host in the notification configuration
      local hst = data.host[d.host_id]
      -- We check that
      --   the host was found
      --   the state matches with the notification configuration
      --   the state is HARD
      --   the change just occured
      if hst then
        for _, hnotif in ipairs(hst.notification) do
          broker_log:info(3, "Host " .. d.host_id ..
                             " found -- state = " .. d.state ..
                             " -- type = " .. d.state_type ..
                             " -- last_check = " .. d.last_check ..
                             " -- last_hard_state_change = " .. d.last_hard_state_change ..
                             " ; notif flags: " .. ((1 << d.state) & hnotif.events))
          if ((1 << d.state) & hnotif.events) ~= 0 and d.state_type == 1 and d.last_check <= d.last_hard_state_change then
            broker_log:info(2, "Notification on host " .. d.host_id ..
                               " --- notification_id: " .. hnotif.notification_id ..
                               " state: " .. d.state)

            local notif = get_notification_rule(hnotif.notification_id)
            if is_notifiable(notif) == false then
            	goto continue
            end

            if notif.channels and notif.channels.email then
              notif = notif.channels.email
              local host = {
                name = hst.name,
                alias = hst.alias,
              }
              send_mail(notif, d, host)
            end
          end

          ::continue::
        end
      else
        broker_log:info(2, "host " .. d.host_id .. " not found")
      end
    else
      if d.scheduled_downtime_depth > 0 then
        broker_log:info(2, "Host " .. d.host_id .. " in downtime, so no notification")
      elseif d.acknowledgement_type ~= 0 then
        broker_log:info(2, "Host " .. d.host_id .. " acknowledged, so no notification")
      end
    end
  end
  return true
end

function flush()
  return true
end
