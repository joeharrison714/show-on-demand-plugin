<h1>Show On Demand</h1>

<h2>How to set up:</h2>
<ol>
<li>Configure voip.ms account</li>
    <ol>
        <li>Create a voip.ms account</li>
        <li>Order a DID number (aka phone number)</li>
        <li>For routing, you can choose "System", "Hang up"</li>
        <li>Edit the DID and ensure SMS messages are enabled</li>
        <li>Enable the Rest API, create an API password, and authorize your IP address (The external IP where your fpp will be making requests from)</li>
    </ol>
<li>Create an on-demand sequence</li>
    <ol>
        <li>Create a sequence to run while the show is in on-demand mode. Perhaps something pleasant that showcases your display without being too flashy.</li>
        <li>Create a voiceover to be played with the sequence that viewers will hear with instructions on how to start the show. Include the number to text as well as the start command. It is suggested that you indicate that the show will be started automatically within 30 seconds (to avoid people impatiently sending multiple messages because they think it’s not working)</li>
    </ol>
<li>Configure FPP</li>
    <ol>
        <li>Create a playlist to be your on-demand playlist and a playlist to be your main show playlist.</li>
        <li>Schedule the on-demand playlist for the times you want your display to be on-demand</li>
    </ol>
<li>Configure the plugin</li>
    <ol>
        <li>Enter your voip.ms username and API password that you created in step 1</li>
        <li>Choose a start command, the message that must be received to start the show</li>
        <li>Fill out the message text you want to be sent back to the viewer</li>
        <li>Select the playlists to be used</li>
        <li>Enable the plug-in</li>
    </ol>
</ol>

<h3>Notes</h3>
<ul>
<li>Check the show-on-demand.log file in file manager for troubleshooting</li>
<li>All received messages will be stored in show-on-demand-messages.csv file in the logs directory</li>
</ul>

<h2>More Info</h2>
<ul>
<li><a href="https://github.com/joeharrison714/show-on-demand-plugin">More info</a></li>
<li><a href="https://github.com/joeharrison714/show-on-demand-plugin/issues">Bug report/Feature request</a></li>
</ul>