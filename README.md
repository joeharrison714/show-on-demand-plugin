# show-on-demand-plugin
This is a Falcon Player (fpp) plugin that allows you to run your show in "On-Demand Mode" where you have an on-demand sequence running and viewers can then start the main show via text message.

## Installation
In fpp, go to Content Setup, Plugin Manager and paste the following URL in the box and click "Retrieve Plugin Info":
`https://raw.githubusercontent.com/joeharrison714/show-on-demand-plugin/master/pluginInfo.json`

## Motivation
Traffic to my display is fairly light in early December, and for some unknown reason I just don’t like the idea of my show continuously running on a loop when no one is out there watching. So I wanted to create a simple and easy way to have a basic sequence running but allow people to start the show when they are ready.

## How It Works
1. You would first create and schedule a sequence that you want to run while your show is in “On-Demand Mode”. The idea here is that you would have some basic animation running to draw attention to your display, and an audio track in which you would instruct viewers to text a certain word or phrase to a specific number to start the show.
1. The plugin will poll for new messages every 30 seconds. When it receives the correct word to start the show, it will start the show playlist
1. After the show playlist is complete, the on-demand sequence will resume (according to its schedule)

### Notes:
- The plugin will only poll for new messages while fpp is playing. There is no need for it to make the api calls to retrieve new messages while idle.
- When the start word is received, the plugin will check the current running playlist to ensure it is the on-demand playlist before starting the show playlist. (This would prevent someone from starting the show during a time when you do not want it to be on-demand)

## How to set up:
- Configure voip.ms account
  - Create a voip.ms account
  - Order a DID number (aka phone number)
  - For routing, you can choose "System", "Hang up"
  - Edit the DID and ensure SMS messages are enabled
  - Enable the Rest API, create an API password, and authorize your IP address (The external IP where your fpp will be making requests from)
- Create an on-demand sequence
  - Create a sequence to run while the show is in on-demand mode. Perhaps something pleasant that showcases your display without being too flashy.
  - Create a voiceover to be played with the sequence that viewers will hear with instructions on how to start the show. Include the number to text as well as the start command. It is suggested that you indicate that the show will be started automatically within 30 seconds (to avoid people impatiently sending multiple messages because they think it’s not working)
- Configure FPP
  - Create a playlist to be your on-demand playlist and a playlist to be your main show playlist.
  - Schedule the on-demand playlist for the times you want your display to be on-demand
  - Configure the plugin
  - Enter your voip.ms username and API password that you created in step 1
  - Choose a start command, the message that must be received to start the show
  - Fill out the message text you want to be sent back to the viewer
  - Select the playlists to be used
  - Enable the plug-in

### Notes
- Check the show-on-demand.log file in file manager for troubleshooting
- All received messages will be stored in show-on-demand-messages.csv file in the logs directory
