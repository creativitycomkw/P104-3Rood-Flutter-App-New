import 'package:flutter/cupertino.dart';

import 'package:onesignal_flutter/onesignal_flutter.dart';

class OneSignalService {
  // Initialize OneSignal
  Future<dynamic> init() async {
    OneSignal.Debug.setLogLevel(OSLogLevel.verbose);
    OneSignal.Debug.setAlertLevel(OSLogLevel.none);
    OneSignal.consentRequired(false); // Change to true if needed
    OneSignal.initialize("632c757a-7244-4842-a374-ec791b66e1b4");
    sendTags(key: "group", value: "customer");
    sendTags(key: "is_logged_in", value: "no");
    sendTags(key: "user_name", value: "");
    sendTags(key: "cart_update", value: "");

    OneSignal.Notifications.addClickListener((event) {
      // Get.to(NotificationScreen());
    });

    OneSignal.User.pushSubscription.addObserver((state) {
      print("object");
      print("opted in ${OneSignal.User.pushSubscription.optedIn}");
      print(" subscription id ${OneSignal.User.pushSubscription.id}");
      print("token ${OneSignal.User.pushSubscription.token}");
      print("onse signal json value ${state.current.jsonRepresentation()}");

    });
  }

  // Add other methods you need to use as service methods
  void sendTags({required String key, required String value}) {
    debugPrint("Sending tags");
    OneSignal.User.addTagWithKey(key, value);
  }

  void setExternalUserId(String id,{String name='NA'}) {
   
    print("setting onesignal id ${id}");
    OneSignal.login(id);

    OneSignal.User.addAlias("token",id);
    sendTags(key: 'is_logged_in', value: 'yes');
    sendTags(key: 'user_name', value:name);
  }

  void removeExternalUserId(String id) {
    OneSignal.logout();
    OneSignal.User.removeAlias(id);
    sendTags(key: 'is_logged_in', value: 'no');
    sendTags(key: 'user_name', value: '');
  }

  void updateCart(
      {cart_count = null, product_name = null, product_image = null}) {
    if (cart_count == null) {
      sendTags(key: 'cart_update', value: '');
      sendTags(key: 'cart_count', value: '');
      sendTags(key: 'product_name', value: '');
      sendTags(key: 'product_image', value: '');
    } else {
      sendTags(
          key: 'cart_update',
          value: (DateTime.now().millisecondsSinceEpoch ~/ 100).toString());
      sendTags(key: 'cart_count', value: cart_count);

      if (product_name != null) {
        sendTags(key: 'product_name', value: product_name);
      }

      if (product_image != null) {
        sendTags(key: 'product_image', value: product_image);
      }
    }
  }

  void setLanguage(String language) {
    if (language == "null") return;
    OneSignal.User.setLanguage(language);
  }

  capitalize(s) {
    // returns the first letter capitalized + the string from index 1 and out aka. the rest of the string
    return s[0].toUpperCase() + s.substr(1);
  }

// use to request permission
  void promptForPushPermission() async {
    print("Prompting foronesignal notiication Permission");
    await OneSignal.Notifications.requestPermission(true).then((value) {
      print("value of permission is $value");
      init();
    });
  }

  //use to start receiving push notification
  subscribe() {
    OneSignal.User.pushSubscription.optIn().then((value) {});
  }

  //use to stop receiving push notification
  void unSubscribe() {
    OneSignal.User.pushSubscription.optOut();
  }
}
