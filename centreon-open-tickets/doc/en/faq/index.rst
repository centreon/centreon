###
FAQ
###

---------------------------------------------------
Can i use a script to open a ticket automatically ?
---------------------------------------------------

In the current version of the module, no. 
But we think about it and in the future, it will be. 

--------------------------------------------------------------------------------------------
Can i close a ticket from centreon-web and it will be also closed in the ticketting system ? 
--------------------------------------------------------------------------------------------

Yes, if the provider had that capabilities. In the current version, you can only does if you use ``OTRS``.
You can enable it if you check the ``Close ticket`` attribute in your ``centreon-open-tickets`` rules.

------------------------------------
Can i display open tickets history ?
------------------------------------

Yes, goes to **Monitoring > Event logs > Ticket Logs** page.

---------------------------------------------
How can i add service graphics in my ticket ?
---------------------------------------------

Yes it can be if your ticketting system understand the html ``img`` tag.

To display the service graphics, we use Centreon autologin system. You can enable it
in **Administration > Parameters > Centreon UI** with the checkbox ``Enable Autologin``.
The next step is to connect with a privileged user (with read access on services) and 
go in account page to generate an autologin key.

You can configure the ``Body list definition`` in your ``centreon-open-tickets`` rule and that 3 lines:
::

    {assign var="centreon_url" value="localhost"}
    {assign var="centreon_username" value="admin"}
    {assign var="centreon_token" value="token"}
    
* **centreon_url** : replace it by your centreon-web server address
* **centreon_username** and **centreon_token** : replace it by the user and the token

The last step is to enabled (remove html comments) following lines in ``Formatting popup`` textarea (``advanced`` tab):
::

    <tr>
        <td class="FormRowField" style="padding-left:15px;">Add graphs</td>
        <td class="FormRowValue" style="padding-left:15px;"><input type="checkbox" name="add_graph" value="1" /></td>
    </tr>