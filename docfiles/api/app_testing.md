/*
Title: Testing App Features
*/

# Testing App Features

To test your app, you will need Xcode for iOS, and Eclipse for Android.  You can test without knowing much about these systems.

Download the AppPresser project folder, or create your own Phonegap project.  Creating your own project is pretty easy, just a few simple commands on the command line. More information here: http://docs.phonegap.com/en/3.1.0/guide_cli_index.md.html#The%20Command-line%20Interface

## iOS/Xcode

In your project, or the AppPresser project, navigate to platforms/ios, then open the Xcode project file.  You should see a config.xml file in the file navigator to the left, click that.  Find the line that says content src="http://app.wdslab.com/?appp=1", and change that to your site url with ?appp=1 at the end.

Save and click the play button at the top left.  That’s it! Your app will pop up and you can test away. To test on an actual device, you need a developer license, then you need to provision your device and set it up for testing.

The camera does not work in the simulator, but uploading an image does work. To add an image to your simulated device, open safari in the simulator, then drag an image into safari, and save it to the image library. You can now test uploading an image.

Note: if you are using https, you may need to make ALL links, including the content src url https.  This is due to the same domain origin policy, which does not allow ajax to load https content from a non https page.

Here are the [PhoneGap project files](http://apppresser.com/wp-content/uploads/2013/12/apppresser-pg.zip) so you can see what all goes on with this part of the process.
