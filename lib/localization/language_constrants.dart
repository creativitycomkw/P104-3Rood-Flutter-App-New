import 'package:flutter/material.dart';
import 'package:flutter_ecommerce/localization/app_localization.dart';

String? getTranslated(String? key, BuildContext context) {
  String? text = key;
  try{
    text = AppLocalization.of(context)!.translate(key);
  }catch (error){
    text = "$key";
  }
  return text;
}